<?php

namespace RestApiToolkit\Admin\Pages;

/**
 * Dashboard con métricas de observabilidad basadas en wp_rat_api_logs.
 */
class DashboardPage
{
    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'rat_api_logs';
        $today = gmdate('Y-m-d 00:00:00');

        // phpcs:disable WordPress.DB
        $requestsToday = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", $today));
        $errorsToday   = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND status_code >= 400", $today));
        $rateLimited   = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND status_code = 429", $today));
        $avgLatency    = (float) $wpdb->get_var($wpdb->prepare("SELECT AVG(duration) FROM {$table} WHERE created_at >= %s", $today));
        $topRoutes     = $wpdb->get_results($wpdb->prepare(
            "SELECT route, method, COUNT(*) AS hits FROM {$table} WHERE created_at >= %s GROUP BY route, method ORDER BY hits DESC LIMIT 8",
            gmdate('Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS)
        ));
        // phpcs:enable

        $baseUrl = rest_url(RAT_REST_NAMESPACE);
        ?>
        <div class="wrap rat-wrap">
            <h1><?php esc_html_e('REST API Toolkit — Dashboard', 'rest-api-toolkit'); ?></h1>

            <p>
                <?php esc_html_e('URL base de la API:', 'rest-api-toolkit'); ?>
                <code><?php echo esc_html($baseUrl); ?></code>
                &nbsp;|&nbsp;
                <a href="<?php echo esc_url($baseUrl . '/openapi.json'); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('Especificación OpenAPI', 'rest-api-toolkit'); ?>
                </a>
            </p>

            <div class="rat-cards">
                <div class="rat-card">
                    <span class="rat-card-value"><?php echo esc_html(number_format_i18n($requestsToday)); ?></span>
                    <span class="rat-card-label"><?php esc_html_e('Requests hoy', 'rest-api-toolkit'); ?></span>
                </div>
                <div class="rat-card <?php echo $errorsToday > 0 ? 'rat-card-warn' : ''; ?>">
                    <span class="rat-card-value"><?php echo esc_html(number_format_i18n($errorsToday)); ?></span>
                    <span class="rat-card-label"><?php esc_html_e('Errores (4xx/5xx) hoy', 'rest-api-toolkit'); ?></span>
                </div>
                <div class="rat-card">
                    <span class="rat-card-value"><?php echo esc_html(number_format_i18n($rateLimited)); ?></span>
                    <span class="rat-card-label"><?php esc_html_e('Respuestas 429 hoy', 'rest-api-toolkit'); ?></span>
                </div>
                <div class="rat-card">
                    <span class="rat-card-value"><?php echo esc_html($avgLatency ? round($avgLatency * 1000) . ' ms' : '—'); ?></span>
                    <span class="rat-card-label"><?php esc_html_e('Latencia media hoy', 'rest-api-toolkit'); ?></span>
                </div>
            </div>

            <h2><?php esc_html_e('Endpoints más usados (7 días)', 'rest-api-toolkit'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Método', 'rest-api-toolkit'); ?></th>
                        <th><?php esc_html_e('Ruta', 'rest-api-toolkit'); ?></th>
                        <th><?php esc_html_e('Peticiones', 'rest-api-toolkit'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$topRoutes) : ?>
                        <tr><td colspan="3"><?php esc_html_e('Sin actividad registrada todavía.', 'rest-api-toolkit'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($topRoutes as $row) : ?>
                            <tr>
                                <td><code><?php echo esc_html($row->method); ?></code></td>
                                <td><code><?php echo esc_html($row->route); ?></code></td>
                                <td><?php echo esc_html(number_format_i18n((int) $row->hits)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <p class="rat-donate-hint">
                <?php esc_html_e('¿Te resulta útil el plugin?', 'rest-api-toolkit'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rat-donations')); ?>">
                    <?php esc_html_e('Apóyalo con una donación ❤', 'rest-api-toolkit'); ?>
                </a>
            </p>
        </div>
        <?php
    }
}
