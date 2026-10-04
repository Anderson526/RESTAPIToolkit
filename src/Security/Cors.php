<?php

namespace RestApiToolkit\Security;

use RestApiToolkit\Core\Config;

/**
 * CORS para el namespace del toolkit. Solo permite orígenes configurados
 * explícitamente; nunca "*" con credenciales.
 */
class Cors
{
    public function register(): void
    {
        add_action('rest_api_init', function () {
            add_filter('rest_pre_serve_request', [$this, 'sendHeaders'], 15, 4);
        }, 15);
    }

    /**
     * @param bool              $served
     * @param \WP_HTTP_Response $result
     * @param \WP_REST_Request  $request
     * @param \WP_REST_Server   $server
     */
    public function sendHeaders($served, $result, $request, $server): bool
    {
        if (strpos((string) $request->get_route(), '/' . RAT_REST_NAMESPACE) !== 0) {
            return $served;
        }

        $origin = get_http_origin();
        if (!$origin) {
            return $served;
        }

        $allowed = array_filter(array_map('trim', explode("\n", (string) Config::get('cors_origins', ''))));

        if (!in_array($origin, $allowed, true)) {
            return $served;
        }

        header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
        header('Vary: Origin', false);
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-RAT-Key, X-WP-Nonce');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Expose-Headers: X-Request-ID, X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset');

        return $served;
    }
}
