<?php

namespace App\DTO;

use Carbon\Carbon;

readonly class IpCheckResultDTO
{
    public function __construct(
        public string $ip,
        public string $city,
        public string $country,
        public string $countryCode,
        public string $isp,
        public bool $isProtected,
        public Carbon $checkedAt,
    ) {}

    public function getFlag(): string
    {
        // Convert country code to flag emoji
        $code = strtoupper($this->countryCode);
        if (strlen($code) !== 2) {
            return '';
        }

        $first = mb_chr(ord($code[0]) - ord('A') + 0x1F1E6);
        $second = mb_chr(ord($code[1]) - ord('A') + 0x1F1E6);

        return $first . $second;
    }

    public function getMaskedIp(): string
    {
        $parts = explode('.', $this->ip);
        if (count($parts) === 4) {
            return "{$parts[0]}.{$parts[1]}.xxx.xxx";
        }
        return $this->ip;
    }

    public function toArray(): array
    {
        return [
            'ip' => $this->ip,
            'city' => $this->city,
            'country' => $this->country,
            'countryCode' => $this->countryCode,
            'isp' => $this->isp,
            'isProtected' => $this->isProtected,
            'checkedAt' => $this->checkedAt->toIso8601String(),
        ];
    }
}
