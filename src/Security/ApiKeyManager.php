<?php

namespace RestApiToolkit\Security;

use RestApiToolkit\REST\Exceptions\AuthenticationException;

/**
 * Gestión de API keys. El secreto solo se muestra una vez y se almacena hasheado (HMAC-SHA256).
 * Formato de la clave completa: rat_{key_id}.{secret}
 */
class ApiKeyManager
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'rat_api_keys';
    }

    /**
     * Crea una API key y devuelve el secreto en texto plano UNA sola vez.
     *
     * @param string[] $scopes Ej: ['*'], ['products.*', 'orders.read']
     * @return array{id: int, key_id: string, plain_key: string}
     */
    public function create(string $name, int $userId, array $scopes = ['*'], ?int $expiresDays = null): array
    {
        global $wpdb;

        $keyId  = 'rat_' . strtolower(wp_generate_password(12, false, false));
        $secret = bin2hex(random_bytes(24));

        $wpdb->insert($this->table(), [
            'key_id'      => $keyId,
            'name'        => sanitize_text_field($name),
            'secret_hash' => $this->hashSecret($secret),
            'user_id'     => $userId,
            'scopes'      => wp_json_encode(array_values(array_map('sanitize_text_field', $scopes))),
            'status'      => 'active',
            'expires_at'  => $expiresDays ? gmdate('Y-m-d H:i:s', time() + $expiresDays * DAY_IN_SECONDS) : null,
            'created_at'  => current_time('mysql', true),
        ]);

        return [
            'id'        => (int) $wpdb->insert_id,
            'key_id'    => $keyId,
            'plain_key' => $keyId . '.' . $secret,
        ];
    }

    /**
     * Verifica una clave completa "rat_xxx.secret".
     *
     * @return object Fila de la key con scopes decodificados.
     * @throws AuthenticationException
     */
    public function verify(string $rawKey): object
    {
        global $wpdb;

        $parts = explode('.', $rawKey, 2);
        if (count($parts) !== 2 || strpos($parts[0], 'rat_') !== 0) {
            throw new AuthenticationException(__('API key con formato inválido.', 'rest-api-toolkit'));
        }

        [$keyId, $secret] = $parts;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE key_id = %s", $keyId) // phpcs:ignore WordPress.DB
        );

        if (!$row || $row->status !== 'active') {
            throw new AuthenticationException(__('API key inválida o revocada.', 'rest-api-toolkit'));
        }

        if ($row->expires_at && strtotime($row->expires_at . ' UTC') < time()) {
            throw new AuthenticationException(__('API key expirada.', 'rest-api-toolkit'));
        }

        if (!hash_equals($row->secret_hash, $this->hashSecret($secret))) {
            throw new AuthenticationException(__('API key inválida.', 'rest-api-toolkit'));
        }

        $wpdb->update(
            $this->table(),
            ['last_used_at' => current_time('mysql', true)],
            ['id' => $row->id]
        );

        $row->scopes = json_decode((string) $row->scopes, true) ?: [];

        return $row;
    }

    public function revoke(int $id): bool
    {
        global $wpdb;

        return (bool) $wpdb->update($this->table(), ['status' => 'revoked'], ['id' => $id]);
    }

    /** @return object[] */
    public function all(): array
    {
        global $wpdb;

        return (array) $wpdb->get_results(
            "SELECT id, key_id, name, user_id, scopes, status, last_used_at, expires_at, created_at
             FROM {$this->table()} ORDER BY created_at DESC" // phpcs:ignore WordPress.DB
        );
    }

    private function hashSecret(string $secret): string
    {
        return hash_hmac('sha256', $secret, wp_salt('auth'));
    }
}
