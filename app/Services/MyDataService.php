<?php

namespace App\Services;

use App\DTO\MyDataDTO;
use App\Models\CommunityProbeSignal;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Surfaces a user's own contributed probe signals back to them (transparency +
 * right-to-delete) and is the only service that should write `deleted_at` on
 * `community_probe_signals`. Every read path filters out tombstoned rows.
 */
class MyDataService
{
    public function summary(int $telegramId, int $page = 1, int $perPage = 30): MyDataDTO
    {
        if (!Schema::hasTable('community_probe_signals')) {
            return $this->empty($page, $perPage);
        }

        $hash = CommunityProbeSignal::hashForUser($telegramId);
        $base = CommunityProbeSignal::query()->where('user_hash', $hash)->whereNull('deleted_at');

        $total = (int) (clone $base)->count();
        if ($total === 0) {
            return $this->empty($page, $perPage);
        }

        $first = (clone $base)->min('observed_at');
        $last  = (clone $base)->max('observed_at');
        $distinctUrls = (int) (clone $base)->distinct('url')->count('url');
        $reachable = (int) (clone $base)->where('result', 'reachable')->count();
        $blocked   = (int) (clone $base)->where('result', 'blocked')->count();
        $distinctNetworks = (int) (clone $base)
            ->selectRaw('country_code, asn')
            ->distinct()
            ->count();

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $rows = (clone $base)
            ->orderByDesc('observed_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get(['id', 'url', 'service_key', 'country_code', 'asn', 'result', 'observed_at']);

        $recent = $rows->map(fn ($r) => [
            'id' => $r->id,
            'url' => $r->url,
            'host' => parse_url($r->url, PHP_URL_HOST),
            'serviceKey' => $r->service_key,
            'country' => $r->country_code,
            'asn' => $r->asn,
            'result' => $r->result,
            'observedAt' => optional($r->observed_at)->toIso8601String(),
        ])->all();

        $totalPages = (int) ceil($total / $perPage);

        return new MyDataDTO(
            totalSignals: $total,
            firstSeenAt: $first instanceof Carbon ? $first->toIso8601String() : ($first ? (string) $first : null),
            lastSeenAt: $last instanceof Carbon ? $last->toIso8601String() : ($last ? (string) $last : null),
            distinctUrls: $distinctUrls,
            reachableCount: $reachable,
            blockedCount: $blocked,
            distinctNetworks: $distinctNetworks,
            recentSignals: $recent,
            page: $page,
            perPage: $perPage,
            totalPages: $totalPages,
            hasMore: $page < $totalPages,
        );
    }

    public function softPurge(int $telegramId): int
    {
        if (!Schema::hasTable('community_probe_signals')) {
            return 0;
        }
        $hash = CommunityProbeSignal::hashForUser($telegramId);
        return CommunityProbeSignal::query()
            ->where('user_hash', $hash)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => Carbon::now()]);
    }

    /**
     * @return iterable<array> — generator for streamed download; caller JSON-encodes.
     */
    public function export(int $telegramId): iterable
    {
        if (!Schema::hasTable('community_probe_signals')) {
            yield from [];
            return;
        }
        $hash = CommunityProbeSignal::hashForUser($telegramId);
        foreach (
            CommunityProbeSignal::query()
                ->where('user_hash', $hash)
                ->whereNull('deleted_at')
                ->orderBy('observed_at')
                ->cursor() as $r
        ) {
            yield [
                'url' => $r->url,
                'service_key' => $r->service_key,
                'country_code' => $r->country_code,
                'asn' => $r->asn,
                'result' => $r->result,
                'observed_at' => optional($r->observed_at)->toIso8601String(),
                'created_at' => optional($r->created_at)->toIso8601String(),
            ];
        }
    }

    private function empty(int $page, int $perPage): MyDataDTO
    {
        return new MyDataDTO(
            totalSignals: 0,
            firstSeenAt: null,
            lastSeenAt: null,
            distinctUrls: 0,
            reachableCount: 0,
            blockedCount: 0,
            distinctNetworks: 0,
            recentSignals: [],
            page: max(1, $page),
            perPage: $perPage,
            totalPages: 0,
            hasMore: false,
        );
    }
}
