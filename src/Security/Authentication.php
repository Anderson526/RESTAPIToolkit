<?php

namespace RestApiToolkit\Security;

use RestApiToolkit\REST\ApiRequest;
use RestApiToolkit\REST\Exceptions\AuthenticationException;

/**
 * Autenticación soportada:
 *  - API Keys propias (header X-RAT-Key o Authorization: Bearer rat_xxx.secret).
 *  - Cookies + nonce y Application Passwords (resueltas por el core de WP).
 */
class Authentication
{
    /** @var ApiKeyManager */
    private $apiKeys;

    public function __construct(ApiKeyManager $apiKeys)
    {
        $this->apiKeys = $apiKeys;
    }

    /**
     * @throws AuthenticationException Si la ruta no es pública y no hay credenciales válidas.
     */
    public function authenticate(ApiRequest $request, bool $isPublic): void
    {
        $rawKey = $this->extractApiKey($request);

        if ($rawKey !== null) {
            $key = $this->apiKeys->verify($rawKey); // lanza AuthenticationException si es inválida
            $request->setApiKey($key);
            if ((int) $key->user_id > 0) {
                wp_set_current_user((int) $key->user_id);
            }
            $request->setUser(wp_get_current_user());
            return;
        }

        // Autenticación nativa de WP (cookie+nonce validada por rest_cookie_check_errors,
        // o Application Passwords via determine_current_user).
        if (get_current_user_id() > 0) {
            $request->setUser(wp_get_current_user());
            return;
        }

        if (!$isPublic) {
            throw new AuthenticationException();
        }
    }

    private function extractApiKey(ApiRequest $request): ?string
    {
        $header = $request->header('x-rat-key');
        if ($header) {
            return trim($header);
        }

        $authorization = $request->header('authorization');
        if ($authorization && preg_match('/^Bearer\s+(rat_[A-Za-z0-9]+\.[A-Fa-f0-9]+)$/', trim($authorization), $m)) {
            return $m[1];
        }

        return null;
    }
}
