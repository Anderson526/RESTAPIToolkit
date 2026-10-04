<?php

namespace RestApiToolkit\REST;

/**
 * Fábrica de respuestas REST estandarizadas.
 */
class ApiResponse
{
    /**
     * { "success": true, "data": ..., "meta": {...} }
     */
    public static function success($data = null, array $meta = [], int $status = 200): \WP_REST_Response
    {
        $payload = ['success' => true, 'data' => $data];
        if ($meta) {
            $payload['meta'] = $meta;
        }

        return new \WP_REST_Response($payload, $status);
    }

    public static function created($data = null, array $meta = []): \WP_REST_Response
    {
        return self::success($data, $meta, 201);
    }

    public static function noContent(): \WP_REST_Response
    {
        return new \WP_REST_Response(null, 204);
    }

    /**
     * { "success": false, "error": { "code": ..., "message": ..., "details": {...} } }
     */
    public static function error(string $code, string $message, int $status = 400, array $details = []): \WP_REST_Response
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details) {
            $error['details'] = $details;
        }

        return new \WP_REST_Response(['success' => false, 'error' => $error], $status);
    }

    /**
     * Colección paginada con meta estándar.
     *
     * @param array<int, mixed> $items
     */
    public static function paginated(array $items, int $page, int $perPage, int $total): \WP_REST_Response
    {
        return self::success(array_values($items), [
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
        ]);
    }
}
