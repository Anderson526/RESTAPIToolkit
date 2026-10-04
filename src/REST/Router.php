<?php

namespace RestApiToolkit\REST;

use RestApiToolkit\Core\Container;
use RestApiToolkit\Logging\Logger;
use RestApiToolkit\REST\Exceptions\ApiException;
use RestApiToolkit\Security\Authentication;
use RestApiToolkit\Security\PermissionManager;
use RestApiToolkit\Security\RateLimiter;
use RestApiToolkit\Validation\Validator;

/**
 * Router propio sobre register_rest_route() con pipeline:
 * Authentication -> RateLimit -> Authorization -> Validation -> Controller.
 */
class Router
{
    /** @var Container */
    private $container;

    /** @var string */
    private $namespace;

    /** @var array<int, array{methods: string[], path: string, handler: mixed, options: array}> */
    private $routes = [];

    public function __construct(Container $container, string $namespace)
    {
        $this->container = $container;
        $this->namespace = $namespace;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /** @return array<int, array> */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function get(string $path, $handler, array $options = []): void
    {
        $this->add(['GET'], $path, $handler, $options);
    }

    public function post(string $path, $handler, array $options = []): void
    {
        $this->add(['POST'], $path, $handler, $options);
    }

    public function put(string $path, $handler, array $options = []): void
    {
        $this->add(['PUT', 'PATCH'], $path, $handler, $options);
    }

    public function patch(string $path, $handler, array $options = []): void
    {
        $this->add(['PATCH'], $path, $handler, $options);
    }

    public function delete(string $path, $handler, array $options = []): void
    {
        $this->add(['DELETE'], $path, $handler, $options);
    }

    /**
     * @param string[] $methods
     * @param callable|string|array $handler 'Controller@method' o [Clase::class, 'method']
     * @param array $options {public?: bool, permission?: string, schema?: array, summary?: string}
     */
    public function add(array $methods, string $path, $handler, array $options = []): void
    {
        $this->routes[] = [
            'methods' => $methods,
            'path'    => $path,
            'handler' => $handler,
            'options' => $options,
        ];
    }

    /**
     * Registra todas las rutas en el REST API de WordPress (hook rest_api_init).
     */
    public function register(): void
    {
        foreach ($this->routes as $route) {
            register_rest_route($this->namespace, $this->compilePath($route['path']), [
                'methods'  => implode(',', $route['methods']),
                'callback' => function (\WP_REST_Request $request) use ($route) {
                    return $this->dispatch($route, $request);
                },
                // La autenticación/autorización se gestiona en el pipeline interno.
                'permission_callback' => '__return_true',
            ]);
        }
    }

    private function compilePath(string $path): string
    {
        return preg_replace_callback('#\{([a-zA-Z_]+)\}#', static function ($m) {
            $pattern = in_array($m[1], ['id', 'product'], true) ? '\d+' : '[a-zA-Z0-9_\-]+';
            return '(?P<' . $m[1] . '>' . $pattern . ')';
        }, $path);
    }

    private function dispatch(array $route, \WP_REST_Request $wpRequest): \WP_REST_Response
    {
        $start      = microtime(true);
        $requestId  = 'rat_' . substr(md5(uniqid((string) wp_rand(), true)), 0, 12);
        $request    = new ApiRequest($wpRequest, $requestId, $route['path']);
        $rateHeaders = [];

        try {
            do_action('rat_before_request', $request);

            $this->container->get(Authentication::class)
                ->authenticate($request, !empty($route['options']['public']));

            $rateHeaders = $this->container->get(RateLimiter::class)->check($request);

            if (!empty($route['options']['permission'])) {
                $this->container->get(PermissionManager::class)
                    ->authorize($request, $route['options']['permission']);
            }

            if (!empty($route['options']['schema'])) {
                $validated = $this->container->get(Validator::class)
                    ->validate($request->params(), $route['options']['schema']);
                $request->setValidated($validated);
            }

            [$class, $method] = $this->resolveHandler($route['handler']);
            $result = $this->container->get($class)->{$method}($request);

            $response = $result instanceof \WP_REST_Response
                ? $result
                : ApiResponse::success($result);
        } catch (ApiException $e) {
            $response = $e->toResponse();
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[REST API Toolkit] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
            $response = ApiResponse::error(
                'internal_error',
                __('Error interno del servidor.', 'rest-api-toolkit'),
                500
            );
        }

        $response->header('X-Request-ID', $requestId);
        foreach ($rateHeaders as $header => $value) {
            $response->header($header, (string) $value);
        }

        do_action('rat_after_request', $request, $response);

        $this->container->get(Logger::class)
            ->log($request, $response->get_status(), microtime(true) - $start);

        return $response;
    }

    /** @return array{0: class-string, 1: string} */
    private function resolveHandler($handler): array
    {
        if (is_string($handler) && strpos($handler, '@') !== false) {
            return explode('@', $handler, 2);
        }
        if (is_array($handler) && count($handler) === 2) {
            return [$handler[0], $handler[1]];
        }
        throw new \InvalidArgumentException('Handler de ruta inválido. Usa "Clase@metodo" o [Clase::class, "metodo"].');
    }
}
