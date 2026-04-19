<?php

namespace App\Http\Controllers\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\CommunityProbeSignal;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OoniContributeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }
        if (!$user->ooni_contribute) {
            return response()->json(['error' => 'contribute_disabled'], 403);
        }

        // Accept any serviceKey (including '_custom' for free-form URL probes
        // launched from the details-page empty state). The service_key column
        // stays useful for grouping catalog-bound signals; non-catalog probes
        // get bucketed under a single key and are still queryable by URL.
        $data = $request->validate([
            'country' => ['required', 'string', 'size:2'],
            'asn' => ['nullable', 'string', 'max:16'],
            'signals' => ['present', 'array', 'max:40'],
            'signals.*.serviceKey' => ['nullable', 'string', 'max:32'],
            'signals.*.url' => ['required', 'url', 'max:512'],
            'signals.*.result' => ['required', Rule::in(['reachable', 'blocked'])],
            'signals.*.observedAt' => ['nullable', 'date'],
        ]);

        if (empty($data['signals'])) {
            return response()->json(['stored' => 0]);
        }

        $hash = CommunityProbeSignal::hashForUser((int) $user->telegram_id);
        $country = strtoupper($data['country']);
        $asn = $data['asn'] ? strtoupper($data['asn']) : null;
        $now = Carbon::now();

        $rows = array_map(fn ($s) => [
            'user_hash' => $hash,
            'country_code' => $country,
            'asn' => $asn,
            'service_key' => !empty($s['serviceKey']) ? $s['serviceKey'] : '_custom',
            'url' => $s['url'],
            'result' => $s['result'],
            'observed_at' => isset($s['observedAt']) ? Carbon::parse($s['observedAt']) : $now,
            'created_at' => $now,
        ], $data['signals']);

        CommunityProbeSignal::query()->insert($rows);

        return response()->json(['stored' => count($rows)]);
    }
}
