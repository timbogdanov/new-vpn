<?php

namespace App\DTO;

readonly class XuiCredentialsDTO
{
    public function __construct(
        public string $host,
        public int $port,
        public string $path,
        public string $username,
        public string $password,
        public int $inboundId,
    ) {}

    public function fingerprint(): string
    {
        return md5($this->host . '|' . $this->port . '|' . $this->username . '|' . $this->inboundId);
    }
}
