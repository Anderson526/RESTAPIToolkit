<?php

namespace RestApiToolkit\REST\Exceptions;

use RestApiToolkit\REST\ApiResponse;

class RateLimitException extends ApiException
{
    /** @var array<string, string|int> */
    private $headers;

    /** @param array<string, string|int> $headers Headers X-RateLimit-* */
    public function __construct(array $headers = [], string $message = '')
    {
        parent::__construct(
            $message !== '' ? $message : __('Demasiadas peticiones. Inténtalo más tarde.', 'rest-api-toolkit'),
            'rate_limited',
            429
        );
        $this->headers = $headers;
    }

    public function toResponse(): \WP_REST_Response
    {
        $response = ApiResponse::error($this->errorCode, $this->getMessage(), $this->statusCode);
        foreach ($this->headers as $header => $value) {
            $response->header($header, (string) $value);
        }

        return $response;
    }
}
