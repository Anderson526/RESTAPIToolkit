<?php

namespace RestApiToolkit\Admin\Pages;

use RestApiToolkit\Security\ApiKeyManager;

/**
 * Gestión de API keys. El secreto se muestra una única vez tras crearlo.
 */
class ApiKeysPage
{
    /** @var ApiKeyManager */
    private $apiKeys;

    public function __construct(ApiKeyManager $apiKeys)
    {
        $this->apiKeys = $apiKeys;
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $newKey = $this->handleActions();
        $keys   = $this->apiKeys->all();
        ?>
        <div class="wrap rat-wrap">
            <h1><?php esc_html_e('REST API Toolkit — API Keys', 'rest-api-toolkit'); ?></h1>

            <?php if ($newKey) : ?>
                <div class="notice notice-success">
                    <p><strong><?php esc_html_e('API key creada. Cópiala ahora: no volverá a mostrarse.', 'rest-api-toolkit'); ?></strong></p>
                    <p><code class="rat-key-plain"><?php echo esc_html($newKey); ?></code></p>
                    <p>
                        <?php esc_html_e('Uso: header "X-RAT-Key: <clave>" o "Authorization: Bearer <clave>".', 'rest-api-toolkit'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <h2><?php esc_html_e('Crear nueva API key', 'rest-api-toolkit'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('rat_create_key'); ?>
                <input type="hidden" name="rat_action" value="create_key" />
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="rat-key-name"><?php esc_html_e('Nombre', 'rest-api-toolkit'); ?></label></th>
                        <td><input required type="text" id="rat-key-name" name="key_name" class="regular-text" maxlength="191" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rat-key-scopes"><?php esc_html_e('Scopes', 'rest-api-toolkit'); ?></label></th>
                        <td>
                            <input type="text" id="rat-key-scopes" name="key_scopes" class="regular-text" value="*" />
                            <p class="description">
                                <?php esc_html_e('Separados por comas. Ej: products.*, orders.read, posts.create. "*" concede todos.', 'rest-api-toolkit'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rat-key-expires"><?php esc_html_e('Expira en (días)', 'rest-api-toolkit'); ?></label></th>
                        <td>
                            <input type="number" id="rat-key-expires" name="key_expires" min="1" max="3650" class="small-text" />
                            <p class="description"><?php esc_html_e('Vacío = sin expiración.', 'rest-api-toolkit'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Crear API key', 'rest-api-toolkit')); ?>
            </form>

            <h2><?php esc_html_e('Keys existentes', 'rest-api-toolkit'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Nombre', 'rest-api-toolkit'); ?></th>
                        <th><?php esc_html_e('Key ID', 'rest-api-toolkit'); ?></th>
                        <th><?php esc_html_e('Usuario', 'rest-api-toolkit'); ?></th>
                        <th><?php esc_html_e('Scopes', 'rest-api-toolkit'); ?></th>
                        <th><?php esc_html_e('Estado', 'rest-api-toolkit'); ?></th>
                        <th><?php esc_html_e('Último uso', 'rest-api-toolkit'); ?></th>
                        <th><?php esc_html_e('Expira', 'rest-api-toolkit'); ?></th>
                        <th><?php esc_html_e('Acciones', 'rest-api-toolkit'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$keys) : ?>
                        <tr><td colspan="8"><?php esc_html_e('No hay API keys creadas.', 'rest-api-toolkit'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($keys as $key) :
                            $user   = get_user_by('id', (int) $key->user_id);
                            $scopes = json_decode((string) $key->scopes, true) ?: [];
                            ?>
                            <tr>
                                <td><?php echo esc_html($key->name); ?></td>
                                <td><code><?php echo esc_html($key->key_id); ?></code></td>
                                <td><?php echo esc_html($user ? $user->user_login : '—'); ?></td>
                                <td><code><?php echo esc_html(implode(', ', $scopes)); ?></code></td>
                                <td>
                                    <?php echo $key->status === 'active'
                                        ? '<span class="rat-badge rat-badge-ok">' . esc_html__('Activa', 'rest-api-toolkit') . '</span>'
                                        : '<span class="rat-badge rat-badge-off">' . esc_html__('Revocada', 'rest-api-toolkit') . '</span>'; ?>
                                </td>
                                <td><?php echo esc_html($key->last_used_at ?: '—'); ?></td>
                                <td><?php echo esc_html($key->expires_at ?: '—'); ?></td>
                                <td>
                                    <?php if ($key->status === 'active') : ?>
                                        <form method="post" style="display:inline">
                                            <?php wp_nonce_field('rat_revoke_key_' . $key->id); ?>
                                            <input type="hidden" name="rat_action" value="revoke_key" />
                                            <input type="hidden" name="key_id" value="<?php echo esc_attr((string) $key->id); ?>" />
                                            <button type="submit" class="button button-small"
                                                onclick="return confirm('<?php echo esc_js(__('¿Revocar esta API key? Las integraciones que la usen dejarán de funcionar.', 'rest-api-toolkit')); ?>');">
                                                <?php esc_html_e('Revocar', 'rest-api-toolkit'); ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /** @return string|null Clave en texto plano recién creada. */
    private function handleActions(): ?string
    {
        if (empty($_POST['rat_action'])) {
            return null;
        }

        $action = sanitize_key(wp_unslash($_POST['rat_action']));

        if ($action === 'create_key') {
            check_admin_referer('rat_create_key');

            $name = isset($_POST['key_name']) ? sanitize_text_field(wp_unslash($_POST['key_name'])) : '';
            if ($name === '') {
                return null;
            }

            $scopesRaw = isset($_POST['key_scopes']) ? sanitize_text_field(wp_unslash($_POST['key_scopes'])) : '*';
            $scopes    = array_values(array_filter(array_map('trim', explode(',', $scopesRaw)))) ?: ['*'];
            $expires   = !empty($_POST['key_expires']) ? absint($_POST['key_expires']) : null;

            $result = $this->apiKeys->create($name, get_current_user_id(), $scopes, $expires);

            return $result['plain_key'];
        }

        if ($action === 'revoke_key' && !empty($_POST['key_id'])) {
            $id = absint($_POST['key_id']);
            check_admin_referer('rat_revoke_key_' . $id);
            $this->apiKeys->revoke($id);
        }

        return null;
    }
}
