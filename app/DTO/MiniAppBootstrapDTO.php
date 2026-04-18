<?php

namespace App\DTO;

readonly class MiniAppBootstrapDTO
{
    public function __construct(
        public array $user,
        public array $servers,
        public array $config,
    ) {}

    public function toArray(): array
    {
        return [
            'user' => $this->user,
            'servers' => $this->servers,
            'config' => $this->config,
        ];
    }
}
