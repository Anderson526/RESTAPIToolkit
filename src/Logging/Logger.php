<?php

namespace RestApiToolkit\Logging;

use RestApiToolkit\Core\Config;
use RestApiToolkit\REST\ApiRequest;

/**
 * Registro de requests en wp_rat_api_logs. Nunca almacena cuerpos ni secretos.
 */
class Logger
{
    public function log(ApiRequest $request, int $statusCode, float $duration): void
    {
        if (!Config::get('logging_enabled', true)) {
            return;
        }

        global $wpdb;

        $apiKey = $request->apiKey();

        $wpdb->insert($wpdb->prefix . 'rat_api_logs', [
            'request_id'  => $request->id(),
            'method'      => substr($request->method(), 0, 10),
            'route'       => substr($request->routePattern(), 0, 191),
            'user_id'     => $request->user() ? (int) $request->user()->ID : 0,
            'api_key_id'  => $apiKey ? (int) $apiKey->id : null,
            'ip'          => $request->ip(),
            'status_code' => $statusCode,
            'duration'    => round($duration, 5),
            'user_agent'  => $request->userAgent(),
            'created_at'  => current_time('mysql', true),
        ]);
    }

    /**
     * Borra logs antiguos según la retención configurada (cron diario).
     */
    public static function cleanup(): void
    {
        global $wpdb;

        $days = max(1, (int) Config::get('log_retention_days', 30));
        $table = $wpdb->prefix . 'rat_api_logs';

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s", // phpcs:ignore WordPress.DB
                gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS)
            )
        );
    }
}
