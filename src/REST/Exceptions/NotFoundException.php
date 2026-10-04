<?php

namespace RestApiToolkit\REST\Exceptions;

class NotFoundException extends ApiException
{
    public function __construct(string $message = '', string $errorCode = 'not_found')
    {
        parent::__construct(
            $message !== '' ? $message : __('Recurso no encontrado.', 'rest-api-toolkit'),
            $errorCode,
            404
        );
    }
}
