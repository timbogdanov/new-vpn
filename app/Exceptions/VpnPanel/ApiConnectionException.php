<?php

namespace App\Exceptions\VpnPanel;

use Exception;

class ApiConnectionException extends Exception
{
    public function __construct(string $message = 'Cannot connect to 3x-ui API')
    {
        parent::__construct($message);
    }
}
