<?php

namespace App\Services;

use App\Models\Server;

class XuiClientFactory
{
    /** @var array<int, XuiService> */
    private array $cache = [];

    public function forServer(Server $server): XuiService
    {
        if (!isset($this->cache[$server->id])) {
            $this->cache[$server->id] = new XuiService($server->xuiCredentials());
        }
        return $this->cache[$server->id];
    }
}
