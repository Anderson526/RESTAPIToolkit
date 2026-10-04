<?php

namespace RestApiToolkit\REST\Exceptions;

class AuthorizationException extends ApiException
{
    public function __construct(string $message = '')
    {
        parent::__construct(
            $message !== '' ? $message : __('No tienes permisos para realizar esta acción.', 'rest-api-toolkit'),
            'forbidden',
            403
        );
    }
}
