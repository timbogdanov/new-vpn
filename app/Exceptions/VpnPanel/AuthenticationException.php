<?php

namespace App\Exceptions\VpnPanel;

use Exception;

class AuthenticationException extends Exception
{
    public function __construct(string $message = '3x-ui authentication failed')
    {
        parent::__construct($message);
    }
}
