<?php

namespace RestApiToolkit\REST;

/**
 * Abstracción del request. Los servicios nunca acceden a $_POST/$_GET.
 */
class ApiRequest
{
    /** @var \WP_REST_Request */
    private $request;

    /** @var string */
    private $requestId;

    /** @var string Patrón de ruta declarado, ej. /posts/{id} */
    private $routePattern;

    /** @var \WP_User|null */
    private $user;

    /** @var object|null Fila de API key autenticada */
    private $apiKey;

    /** @var array<string, mixed> */
    private $validated = [];

    public function __construct(\WP_REST_Request $request, string $requestId, string $routePattern = '')
    {
        $this->request      = $request;
        $this->requestId    = $requestId;
        $this->routePattern = $routePattern;
    }

    public function id(): string
    {
        return $this->requestId;
    }

    public function method(): string
    {
        return $this->request->get_method();
    }

    public function routePattern(): string
    {
        return $this->routePattern;
    }

    public function query(string $key, $default = null)
    {
        $params = $this->request->get_query_params();
        return array_key_exists($key, $params) ? $params[$key] : $default;
    }

    public function body(string $key, $default = null)
    {
        $params = $this->request->get_json_params();
        if (!is_array($params)) {
            $params = $this->request->get_body_params();
        }
        return is_array($params) && array_key_exists($key, $params) ? $params[$key] : $default;
    }

    public function route(string $key, $default = null)
    {
        $params = $this->request->get_url_params();
        return array_key_exists($key, $params) ? $params[$key] : $default;
    }

    public function header(string $key): ?string
    {
        $value = $this->request->get_header($key);
        return $value === null ? null : (string) $value;
    }

    /** Parámetros combinados (ruta + query + body). */
    public function params(): array
    {
        return (array) $this->request->get_params();
    }

    /** @return array|null Archivo subido ($_FILES) */
    public function file(string $key): ?array
    {
        $files = $this->request->get_file_params();
        return isset($files[$key]) && is_array($files[$key]) ? $files[$key] : null;
    }

    public function ip(): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    public function userAgent(): string
    {
        return isset($_SERVER['HTTP_USER_AGENT'])
            ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 191)
            : '';
    }

    public function setUser(?\WP_User $user): void
    {
        $this->user = $user;
    }

    public function user(): ?\WP_User
    {
        return $this->user;
    }

    public function setApiKey(?object $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function apiKey(): ?object
    {
        return $this->apiKey;
    }

    /** @param array<string, mixed> $validated */
    public function setValidated(array $validated): void
    {
        $this->validated = $validated;
    }

    /**
     * Datos ya validados/sanitizados por el schema. Protege contra mass assignment:
     * solo llegan los campos declarados.
     */
    public function validated(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->validated;
        }
        return array_key_exists($key, $this->validated) ? $this->validated[$key] : $default;
    }

    public function wpRequest(): \WP_REST_Request
    {
        return $this->request;
    }
}
