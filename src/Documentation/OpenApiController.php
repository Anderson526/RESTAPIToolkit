<?php

namespace RestApiToolkit\Documentation;

use RestApiToolkit\REST\ApiRequest;
use RestApiToolkit\REST\Router;

/**
 * Genera la especificación OpenAPI 3.0 a partir de las rutas registradas.
 * GET /wp-json/rat/v1/openapi.json
 */
class OpenApiController
{
    /** @var Router */
    private $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function show(ApiRequest $request): \WP_REST_Response
    {
        $paths = [];

        foreach ($this->router->getRoutes() as $route) {
            $path = $route['path'];
            foreach ($route['methods'] as $method) {
                $operation = [
                    'summary'   => $route['options']['summary'] ?? '',
                    'tags'      => [$this->tagFor($path)],
                    'responses' => $this->defaultResponses($route),
                ];

                $parameters = $this->pathParameters($path);

                $schema = $route['options']['schema'] ?? [];
                if ($schema && in_array($method, ['GET', 'DELETE'], true)) {
                    foreach ($schema as $name => $rules) {
                        $parameters[] = [
                            'name'     => $name,
                            'in'       => 'query',
                            'required' => !empty($rules['required']),
                            'schema'   => $this->fieldSchema($rules),
                        ];
                    }
                } elseif ($schema) {
                    $properties = [];
                    $required   = [];
                    foreach ($schema as $name => $rules) {
                        $properties[$name] = $this->fieldSchema($rules);
                        if (!empty($rules['required'])) {
                            $required[] = $name;
                        }
                    }
                    $body = ['type' => 'object', 'properties' => $properties];
                    if ($required) {
                        $body['required'] = $required;
                    }
                    $operation['requestBody'] = [
                        'required' => true,
                        'content'  => ['application/json' => ['schema' => $body]],
                    ];
                }

                if ($parameters) {
                    $operation['parameters'] = $parameters;
                }

                if (empty($route['options']['public'])) {
                    $operation['security'] = [['ApiKeyAuth' => []], ['ApplicationPassword' => []]];
                }
                if (!empty($route['options']['permission'])) {
                    $operation['description'] = 'Permiso requerido: ' . $route['options']['permission'];
                }

                $paths[$path][strtolower($method)] = $operation;
            }
        }

        $spec = [
            'openapi' => '3.0.3',
            'info'    => [
                'title'       => 'REST API Toolkit',
                'description' => 'API REST de WordPress y WooCommerce generada por REST API Toolkit.',
                'version'     => RAT_VERSION,
            ],
            'servers' => [
                ['url' => rest_url(RAT_REST_NAMESPACE)],
            ],
            'paths'   => $paths,
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in'   => 'header',
                        'name' => 'X-RAT-Key',
                    ],
                    'ApplicationPassword' => [
                        'type'   => 'http',
                        'scheme' => 'basic',
                    ],
                ],
            ],
        ];

        /** Permite extender la especificación generada. */
        $spec = apply_filters('rat_openapi_spec', $spec);

        return new \WP_REST_Response($spec, 200);
    }

    private function tagFor(string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        return $segments[0] ?: 'general';
    }

    private function pathParameters(string $path): array
    {
        $parameters = [];
        if (preg_match_all('#\{([a-zA-Z_]+)\}#', $path, $matches)) {
            foreach ($matches[1] as $name) {
                $parameters[] = [
                    'name'     => $name,
                    'in'       => 'path',
                    'required' => true,
                    'schema'   => ['type' => in_array($name, ['id', 'product'], true) ? 'integer' : 'string'],
                ];
            }
        }

        return $parameters;
    }

    private function fieldSchema(array $rules): array
    {
        $map = [
            'integer' => 'integer',
            'float'   => 'number',
            'number'  => 'number',
            'boolean' => 'boolean',
            'array'   => 'array',
            'email'   => 'string',
            'url'     => 'string',
            'string'  => 'string',
        ];

        $schema = ['type' => $map[$rules['type'] ?? 'string'] ?? 'string'];

        if (($rules['type'] ?? '') === 'email') {
            $schema['format'] = 'email';
        }
        if (($rules['type'] ?? '') === 'url') {
            $schema['format'] = 'uri';
        }
        if (isset($rules['enum'])) {
            $schema['enum'] = array_values($rules['enum']);
        }
        if (isset($rules['default'])) {
            $schema['default'] = $rules['default'];
        }
        if (isset($rules['min'])) {
            $schema['minimum'] = $rules['min'];
        }
        if (isset($rules['max'])) {
            $schema['maximum'] = $rules['max'];
        }
        if ($schema['type'] === 'array') {
            $schema['items'] = ['type' => ($rules['items_type'] ?? '') === 'integer' ? 'integer' : 'string'];
        }

        return $schema;
    }

    private function defaultResponses(array $route): array
    {
        $responses = [
            '200' => ['description' => 'OK'],
            '422' => ['description' => 'Unprocessable Entity'],
            '429' => ['description' => 'Too Many Requests'],
            '500' => ['description' => 'Internal Server Error'],
        ];

        if (empty($route['options']['public'])) {
            $responses['401'] = ['description' => 'Unauthorized'];
            $responses['403'] = ['description' => 'Forbidden'];
        }
        if (strpos($route['path'], '{') !== false) {
            $responses['404'] = ['description' => 'Not Found'];
        }

        return $responses;
    }
}
