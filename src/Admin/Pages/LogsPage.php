<?php

namespace RestApiToolkit\Admin\Pages;

/**
 * Visor de logs de la API con paginación.
 */
class LogsPage
{
    private const PER_PAGE = 50;

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'rat_api_logs';
        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

        // phpcs:disable WordPress.DB
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $logs  = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",
            self::PER_PAGE,
            ($paged - 1) * self::PER_PAGE
        ));
        // phpcs:enable

        $totalPages = (int) ceil($total / self::PER_PAGE);
        ?>
        <div class="wrap rat-wrap">
            <h1><?php esc_html_e('REST API Toolkit — Logs', 'rest-api-toolkit'); ?></h1>
            <p>
                <?php
                printf(
                    /* translators: %s: total de registros */
                    esc_html__('Total de registros: %s. Los logs se limpian automáticamente según la retención configurada.', 'rest-api-toolkit'),
                    esc_html(number_format_i18n($total))
                );
                ?>
            </p>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Fecha (UTC)', 'rest-api-toolkit'); ?></th>
                        <th><?php esc_html_e('Request ID', 'rest-api-toolkit'); ?></th>
                        <th><?php esc_html_e('Método', 'rest-api-toolkit'); ?></th>
                        <th><?php esc_html_e('Ruta', 'rest-api-toolkit'); ?></th>
                        <th><?php esc_html_e('Estado', 'rest-api-toolkit'); ?></th>
                        <th><?php esc_html_e('Duración', 'rest-api-toolkit'); ?></th>
                        <th><?php esc_html_e('Usuario', 'rest-api-toolkit'); ?></th>
                        <th><?php esc_html_e('IP', 'rest-api-toolkit'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$logs) : ?>
                        <tr><td colspan="8"><?php esc_html_e('No hay registros.', 'rest-api-toolkit'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($logs as $log) :
                            $statusClass = $log->status_code >= 500 ? 'rat-badge-err' : ($log->status_code >= 400 ? 'rat-badge-warn' : 'rat-badge-ok');
                            ?>
                            <tr>
                                <td><?php echo esc_html($log->created_at); ?></td>
                                <td><code><?php echo esc_html($log->request_id); ?></code></td>
                                <td><code><?php echo esc_html($log->method); ?></code></td>
                                <td><code><?php echo esc_html($log->route); ?></code></td>
                                <td><span class="rat-badge <?php echo esc_attr($statusClass); ?>"><?php echo esc_html((string) $log->status_code); ?></span></td>
                                <td><?php echo esc_html(round((float) $log->duration * 1000) . ' ms'); ?></td>
                                <td><?php echo esc_html($log->user_id > 0 ? '#' . $log->user_id : '—'); ?></td>
                                <td><?php echo esc_html($log->ip ?: '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        echo wp_kses_post(paginate_links([
                            'base'    => add_query_arg('paged', '%#%'),
                            'format'  => '',
                            'current' => $paged,
                            'total'   => $totalPages,
                        ]));
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
