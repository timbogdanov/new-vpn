<?php

namespace App\DTO;

readonly class ServerDTO
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $country,
        public string $countryCode,
        public ?string $city,
        public string $flagEmoji,
        public array $tags,
        public ?int $loadPercent,
        public ?int $pingMs,
        public bool $isComingSoon,
        public ?string $description,
        public string $protocol,
        public string $hostPreview,
        public int $capacityClients,
        public ?string $statsUpdatedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'country' => $this->country,
            'countryCode' => $this->countryCode,
            'city' => $this->city,
            'flagEmoji' => $this->flagEmoji,
            'tags' => $this->tags,
            'loadPercent' => $this->loadPercent,
            'pingMs' => $this->pingMs,
            'isComingSoon' => $this->isComingSoon,
            'description' => $this->description,
            'protocol' => $this->protocol,
            'hostPreview' => $this->hostPreview,
            'capacityClients' => $this->capacityClients,
            'statsUpdatedAt' => $this->statsUpdatedAt,
        ];
    }
}
