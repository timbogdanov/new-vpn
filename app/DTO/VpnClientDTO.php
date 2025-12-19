<?php

namespace App\DTO;

readonly class VpnClientDTO
{
    public function __construct(
        public string $uuid,
        public string $email,
        public ?int $telegramId,
        public string $subId,
        public bool $enabled,
        public int $expiryTime,
        public int $totalGB,
    ) {}

    public function isExpired(): bool
    {
        if ($this->expiryTime === 0) {
            return false;
        }

        return $this->expiryTime < (time() * 1000);
    }

    public function hasUnlimitedTraffic(): bool
    {
        return $this->totalGB === 0;
    }

    public function hasUnlimitedExpiry(): bool
    {
        return $this->expiryTime === 0;
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'email' => $this->email,
            'telegramId' => $this->telegramId,
            'subId' => $this->subId,
            'enabled' => $this->enabled,
            'expiryTime' => $this->expiryTime,
            'totalGB' => $this->totalGB,
        ];
    }
}
