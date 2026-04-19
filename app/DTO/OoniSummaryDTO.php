<?php

namespace App\DTO;

use Carbon\Carbon;

readonly class OoniSummaryDTO
{
    /**
     * @param  array<int, OoniServiceVerdictDTO>  $services
     */
    public function __construct(
        public string $countryCode,
        public ?string $asn,
        public ?string $asnName,
        public int $lookbackDays,
        public array $services,
        public Carbon $freshAt,
    ) {}

    public function blockedCount(): int
    {
        return count(array_filter(
            $this->services,
            fn (OoniServiceVerdictDTO $s) => $s->status === 'blocked' || $s->status === 'degraded',
        ));
    }

    public function toArray(): array
    {
        return [
            'countryCode' => $this->countryCode,
            'asn' => $this->asn,
            'asnName' => $this->asnName,
            'lookbackDays' => $this->lookbackDays,
            'services' => array_map(fn ($s) => $s->toArray(), $this->services),
            'blockedCount' => $this->blockedCount(),
            'total' => count($this->services),
            'freshAt' => $this->freshAt->toIso8601String(),
        ];
    }
}
