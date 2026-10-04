<?php

namespace RestApiToolkit\Security;

use RestApiToolkit\Core\Config;
use RestApiToolkit\REST\ApiRequest;
use RestApiToolkit\REST\Exceptions\RateLimitException;

/**
 * Rate limiting por ventana fija de 1 minuto. Identidad: API key > usuario > IP.
 */
class RateLimiter
{
    /**
     * @return array<string, int> Headers X-RateLimit-* para la respuesta.
     * @throws RateLimitException
     */
    public function check(ApiRequest $request): array
    {
        $limit = (int) apply_filters(
            'rat_rate_limit',
            (int) Config::get('rate_limit_per_minute', 60),
            $request
        );

        if ($limit <= 0) {
            return []; // rate limiting desactivado
        }

        $window    = (int) floor(time() / MINUTE_IN_SECONDS);
        $reset     = ($window + 1) * MINUTE_IN_SECONDS;
        $bucketKey = 'rat_rl_' . md5($this->identity($request) . '|' . $window);

        $count = (int) get_transient($bucketKey);

        $headers = [
            'X-RateLimit-Limit'     => $limit,
            'X-RateLimit-Remaining' => max(0, $limit - $count - 1),
            'X-RateLimit-Reset'     => $reset,
        ];

        if ($count >= $limit) {
            $headers['X-RateLimit-Remaining'] = 0;
            $headers['Retry-After']           = max(1, $reset - time());
            throw new RateLimitException($headers);
        }

        set_transient($bucketKey, $count + 1, MINUTE_IN_SECONDS + 10);

        return $headers;
    }

    private function identity(ApiRequest $request): string
    {
        $apiKey = $request->apiKey();
        if ($apiKey) {
            return 'key:' . $apiKey->key_id;
        }

        $user = $request->user();
        if ($user && $user->exists()) {
            return 'user:' . $user->ID;
        }

        return 'ip:' . $request->ip();
    }
}
