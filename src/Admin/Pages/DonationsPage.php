<?php

namespace RestApiToolkit\Admin\Pages;

/**
 * Sección de donaciones: enlaces configurables (PayPal, Ko-fi, GitHub Sponsors,
 * Buy Me a Coffee) y otras formas de apoyar el proyecto.
 */
class DonationsPage
{
    public const OPTION = 'rat_donations';

    /** @var array<string, array{label: string, placeholder: string}> */
    private $platforms = [];

    public function __construct()
    {
        $this->platforms = [
            'paypal_url'   => ['label' => 'PayPal', 'placeholder' => 'https://www.paypal.com/donate/?hosted_button_id=...'],
            'kofi_url'     => ['label' => 'Ko-fi', 'placeholder' => 'https://ko-fi.com/tuusuario'],
            'bmc_url'      => ['label' => 'Buy Me a Coffee', 'placeholder' => 'https://buymeacoffee.com/tuusuario'],
            'sponsors_url' => ['label' => 'GitHub Sponsors', 'placeholder' => 'https://github.com/sponsors/tuusuario'],
        ];
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $this->handleSave();
        $options = $this->options();
        ?>
        <div class="wrap rat-wrap">
            <h1><?php esc_html_e('REST API Toolkit — Donaciones', 'rest-api-toolkit'); ?></h1>

            <div class="rat-donate-hero">
                <h2>❤ <?php esc_html_e('Apoya el desarrollo de REST API Toolkit', 'rest-api-toolkit'); ?></h2>
                <p>
                    <?php esc_html_e('REST API Toolkit es software libre y se mantiene gracias a la comunidad. Si este plugin te ahorra tiempo en tus proyectos con WordPress y WooCommerce, considera hacer una donación: ayuda a financiar nuevas funcionalidades, parches de seguridad y soporte continuo.', 'rest-api-toolkit'); ?>
                </p>

                <div class="rat-donate-buttons">
                    <?php foreach ($this->platforms as $key => $platform) : ?>
                        <?php if (!empty($options[$key])) : ?>
                            <a class="button button-primary button-hero rat-donate-btn"
                               href="<?php echo esc_url($options[$key]); ?>"
                               target="_blank" rel="noopener noreferrer">
                                <?php echo esc_html(sprintf(__('Donar vía %s', 'rest-api-toolkit'), $platform['label'])); ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <?php if (!array_filter(array_intersect_key($options, $this->platforms))) : ?>
                    <p><em><?php esc_html_e('Configura al menos un enlace de donación en el formulario de abajo para mostrar los botones.', 'rest-api-toolkit'); ?></em></p>
                <?php endif; ?>
            </div>

            <div class="rat-donate-grid">
                <div class="rat-card rat-card-wide">
                    <h3><?php esc_html_e('Otras formas de apoyar', 'rest-api-toolkit'); ?></h3>
                    <ul class="rat-list">
                        <li>⭐ <?php esc_html_e('Deja una valoración de 5 estrellas en el directorio de plugins.', 'rest-api-toolkit'); ?></li>
                        <li>🐛 <?php esc_html_e('Reporta bugs y propón mejoras en el repositorio del proyecto.', 'rest-api-toolkit'); ?></li>
                        <li>📢 <?php esc_html_e('Comparte el plugin con otros desarrolladores.', 'rest-api-toolkit'); ?></li>
                        <li>🌍 <?php esc_html_e('Contribuye con traducciones a tu idioma.', 'rest-api-toolkit'); ?></li>
                    </ul>
                </div>
            </div>

            <h2><?php esc_html_e('Configurar enlaces de donación', 'rest-api-toolkit'); ?></h2>
            <p class="description">
                <?php esc_html_e('Si distribuyes este plugin o lo usas como base de tu propio producto, configura aquí tus propios enlaces de donación.', 'rest-api-toolkit'); ?>
            </p>

            <form method="post">
                <?php wp_nonce_field('rat_save_donations'); ?>
                <input type="hidden" name="rat_action" value="save_donations" />
                <table class="form-table" role="presentation">
                    <?php foreach ($this->platforms as $key => $platform) : ?>
                        <tr>
                            <th scope="row">
                                <label for="rat-<?php echo esc_attr($key); ?>"><?php echo esc_html($platform['label']); ?></label>
                            </th>
                            <td>
                                <input type="url" class="regular-text"
                                    id="rat-<?php echo esc_attr($key); ?>"
                                    name="<?php echo esc_attr($key); ?>"
                                    value="<?php echo esc_attr($options[$key] ?? ''); ?>"
                                    placeholder="<?php echo esc_attr($platform['placeholder']); ?>" />
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <th scope="row">
                            <label for="rat-donate-message"><?php esc_html_e('Mensaje personalizado', 'rest-api-toolkit'); ?></label>
                        </th>
                        <td>
                            <textarea id="rat-donate-message" name="message" rows="3" class="large-text"><?php echo esc_textarea($options['message'] ?? ''); ?></textarea>
                            <p class="description"><?php esc_html_e('Texto opcional que se mostrará junto a los botones de donación.', 'rest-api-toolkit'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Guardar enlaces', 'rest-api-toolkit')); ?>
            </form>

            <?php if (!empty($options['message'])) : ?>
                <div class="rat-donate-message">
                    <p><?php echo esc_html($options['message']); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /** @return array<string, string> */
    private function options(): array
    {
        $options = get_option(self::OPTION, []);
        return is_array($options) ? $options : [];
    }

    private function handleSave(): void
    {
        if (empty($_POST['rat_action']) || sanitize_key(wp_unslash($_POST['rat_action'])) !== 'save_donations') {
            return;
        }

        check_admin_referer('rat_save_donations');

        $options = [];
        foreach (array_keys($this->platforms) as $key) {
            $url = isset($_POST[$key]) ? esc_url_raw(trim(wp_unslash($_POST[$key]))) : '';
            if ($url !== '' && wp_http_validate_url($url)) {
                $options[$key] = $url;
            }
        }
        $options['message'] = isset($_POST['message'])
            ? sanitize_textarea_field(wp_unslash($_POST['message']))
            : '';

        update_option(self::OPTION, $options);

        add_settings_error('rat_donations', 'saved', __('Enlaces de donación guardados.', 'rest-api-toolkit'), 'success');
        settings_errors('rat_donations');
    }
}
