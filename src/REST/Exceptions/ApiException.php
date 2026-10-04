<?php

namespace RestApiToolkit\REST\Exceptions;

use RestApiToolkit\REST\ApiResponse;

/**
 * Excepción base de la API. Se convierte automáticamente a respuesta REST.
 */
class ApiException extends \Exception
{
    /** @var string */
    protected $errorCode;

    /** @var int */
    protected $statusCode;

    public function __construct(string $message = '', string $errorCode = 'api_error', int $statusCode = 500)
    {
        parent::__construct($message !== '' ? $message : __('Error de API.', 'rest-api-toolkit'));
        $this->errorCode  = $errorCode;
        $this->statusCode = $statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function toResponse(): \WP_REST_Response
    {
        return ApiResponse::error($this->errorCode, $this->getMessage(), $this->statusCode);
    }
}
