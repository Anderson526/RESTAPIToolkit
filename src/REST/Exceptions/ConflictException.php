<?php

namespace RestApiToolkit\REST\Exceptions;

class ConflictException extends ApiException
{
    public function __construct(string $message = '', string $errorCode = 'conflict')
    {
        parent::__construct(
            $message !== '' ? $message : __('El recurso entra en conflicto con el estado actual.', 'rest-api-toolkit'),
            $errorCode,
            409
        );
    }
}
