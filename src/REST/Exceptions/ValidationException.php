<?php

namespace RestApiToolkit\REST\Exceptions;

use RestApiToolkit\REST\ApiResponse;

class ValidationException extends ApiException
{
    /** @var array<string, string[]> */
    private $errors;

    /** @param array<string, string[]> $errors */
    public function __construct(array $errors, string $message = '')
    {
        parent::__construct(
            $message !== '' ? $message : __('Los datos enviados no son válidos.', 'rest-api-toolkit'),
            'validation_failed',
            422
        );
        $this->errors = $errors;
    }

    /** @return array<string, string[]> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function toResponse(): \WP_REST_Response
    {
        return ApiResponse::error($this->errorCode, $this->getMessage(), $this->statusCode, ['fields' => $this->errors]);
    }
}
