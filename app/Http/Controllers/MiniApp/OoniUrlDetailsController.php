<?php

namespace App\Http\Controllers\MiniApp;

use App\DTO\OoniUrlDetailsDTO;
use App\Http\Controllers\Concerns\ResolvesUserNetwork;
use App\Http\Controllers\Controller;
use App\Services\GeoIpService;
use App\Services\OoniSearchService;
use App\Services\OoniService;
use App\Services\OoniUrlNormalizer;
use App\Services\ServerRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OoniUrlDetailsController extends Controller
{
    use ResolvesUserNetwork;

    public function show(
        Request $request,
        GeoIpService $geo,
        OoniService $ooni,
        OoniUrlNormalizer $normalizer,
        OoniSearchService $search,
        ServerRegistryService $registry,
    ): JsonResponse {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:512'],
            'country' => ['nullable', 'string', 'size:2'],
            'asn' => ['nullable', 'string', 'max:16'],
            'days' => ['nullable', 'integer', 'min:7', 'max:60'],
            'force' => ['nullable', 'boolean'],
        ]);

        $normalized = $normalizer->normalize($data['url']);
        if (!$normalized) {
            return response()->json([
                'error' => 'invalid_url',
                'message' => 'Could not recognise that URL',
            ], 422);
        }

        $net = $this->resolveNetwork($request, $geo);
        if (!$net['country']) {
            return response()->json([
                'error' => 'geo_failed',
                'message' => 'Could not detect your network',
            ], 502);
        }

        $details = $ooni->urlDetails(
            normalizedUrl: $normalized,
            countryCode: $net['country'],
            asn: $net['asn'],
            days: $data['days'] ?? null,
            force: (bool) ($data['force'] ?? false),
        );

        // Attach recommended server for blocked/degraded verdicts so the UI
        // can show a one-tap unblock CTA.
        $recommendedSlug = null;
        if (in_array($details->verdictStatus, ['blocked', 'degraded'], true)) {
            $recommendedSlug = $registry->all()
                ->where('is_coming_soon', false)
                ->sortBy('load_percent')
                ->first()?->slug;
        }

        $payload = $details->toArray();
        if (!$payload['asnName']) {
            $payload['asnName'] = $net['asnName'];
        }
        $payload['recommendedServerSlug'] = $recommendedSlug;

        // Record the hit so popular searches climb the typeahead.
        $search->recordSearchHit($normalized, $net['country']);

        return response()->json(['result' => $payload]);
    }
}
