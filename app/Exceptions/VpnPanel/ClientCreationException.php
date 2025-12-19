<?php

namespace App\Exceptions\VpnPanel;

use Exception;

class ClientCreationException extends Exception
{
    public function __construct(string $message = 'Failed to create VPN client')
    {
        parent::__construct($message);
    }
}
