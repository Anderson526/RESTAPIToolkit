<?php

namespace RestApiToolkit\REST\Exceptions;

class AuthenticationException extends ApiException
{
    public function __construct(string $message = '')
    {
        parent::__construct(
            $message !== '' ? $message : __('Autenticación requerida.', 'rest-api-toolkit'),
            'unauthenticated',
            401
        );
    }
}
