<?php

namespace RestApiToolkit\Admin\Pages;

use RestApiToolkit\Core\Config;

/**
 * Ajustes generales del plugin (Settings API).
 */
class SettingsPage
{
    public static function registerSettings(): void
    {
        register_setting('rat_settings_group', Config::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [self::class, 'sanitize'],
            'default'           => [],
        ]);
    }

    /** @param mixed $input */
    public static function sanitize($input): array
    {
        $input = is_array($input) ? $input : [];

        $origins = isset($input['cors_origins']) ? (string) $input['cors_origins'] : '';
        $origins = implode("\n", array_filter(array_map(static function ($line) {
            $line = esc_url_raw(trim($line));
            return $line !== '' ? untrailingslashit($line) : '';
        }, explode("\n", $origins))));

        return [
            'logging_enabled'       => !empty($input['logging_enabled']),
            'log_retention_days'    => min(365, max(1, absint($input['log_retention_days'] ?? 30))),
            'rate_limit_per_minute' => min(100000, absint($input['rate_limit_per_minute'] ?? 60)),
            'cors_origins'          => $origins,
            'show_donation_notice'  => !empty($input['show_donation_notice']),
        ];
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap rat-wrap">
            <h1><?php esc_html_e('REST API Toolkit — Ajustes', 'rest-api-toolkit'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('rat_settings_group'); ?>

                <h2><?php esc_html_e('Logging', 'rest-api-toolkit'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Registrar requests', 'rest-api-toolkit'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(Config::OPTION); ?>[logging_enabled]" value="1" <?php checked((bool) Config::get('logging_enabled', true)); ?> />
                                <?php esc_html_e('Guardar un registro de cada petición a la API (sin cuerpos ni secretos).', 'rest-api-toolkit'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rat-retention"><?php esc_html_e('Retención de logs (días)', 'rest-api-toolkit'); ?></label></th>
                        <td>
                            <input type="number" id="rat-retention" min="1" max="365" class="small-text"
                                name="<?php echo esc_attr(Config::OPTION); ?>[log_retention_days]"
                                value="<?php echo esc_attr((string) Config::get('log_retention_days', 30)); ?>" />
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Seguridad', 'rest-api-toolkit'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="rat-rate-limit"><?php esc_html_e('Rate limit (requests/minuto)', 'rest-api-toolkit'); ?></label></th>
                        <td>
                            <input type="number" id="rat-rate-limit" min="0" max="100000" class="small-text"
                                name="<?php echo esc_attr(Config::OPTION); ?>[rate_limit_per_minute]"
                                value="<?php echo esc_attr((string) Config::get('rate_limit_per_minute', 60)); ?>" />
                            <p class="description"><?php esc_html_e('0 desactiva el rate limiting. Se aplica por API key, usuario o IP.', 'rest-api-toolkit'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rat-cors"><?php esc_html_e('Orígenes CORS permitidos', 'rest-api-toolkit'); ?></label></th>
                        <td>
                            <textarea id="rat-cors" rows="4" class="large-text code"
                                name="<?php echo esc_attr(Config::OPTION); ?>[cors_origins]"><?php echo esc_textarea((string) Config::get('cors_origins', '')); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Un origen por línea, ej: https://app.midominio.com — nunca se usa "*" con credenciales.', 'rest-api-toolkit'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Donaciones', 'rest-api-toolkit'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Aviso de donación', 'rest-api-toolkit'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(Config::OPTION); ?>[show_donation_notice]" value="1" <?php checked((bool) Config::get('show_donation_notice', true)); ?> />
                                <?php esc_html_e('Mostrar enlaces de donación dentro de las páginas del plugin.', 'rest-api-toolkit'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
