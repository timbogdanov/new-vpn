<?php

namespace App\Http\Controllers\MiniApp;

use App\Http\Controllers\Controller;
use App\Services\OoniUrlNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OoniWatchlistController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'services' => array_values(array_unique((array) ($user?->ooni_watchlist ?? []))),
            'urls' => array_values(array_unique((array) ($user?->ooni_watchlist_urls ?? []))),
        ]);
    }

    public function update(Request $request, OoniUrlNormalizer $normalizer): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $validKeys = array_column((array) config('ooni.services', []), 'key');

        $data = $request->validate([
            'services' => ['sometimes', 'array', 'max:20'],
            'services.*' => ['string', Rule::in($validKeys)],
            'urls' => ['sometimes', 'array', 'max:20'],
            'urls.*' => ['string', 'max:512'],
        ]);

        if (!isset($data['services']) && !isset($data['urls'])) {
            return response()->json([
                'error' => 'invalid_payload',
                'message' => 'Provide at least one of {services, urls}',
            ], 422);
        }

        if (isset($data['services'])) {
            $user->ooni_watchlist = array_values(array_unique($data['services']));
        }

        if (isset($data['urls'])) {
            $normalized = [];
            foreach ($data['urls'] as $url) {
                $n = $normalizer->normalize($url);
                if ($n) {
                    $normalized[] = $n;
                }
            }
            $user->ooni_watchlist_urls = array_values(array_unique($normalized));
        }

        $combined = count((array) $user->ooni_watchlist) + count((array) $user->ooni_watchlist_urls);
        if ($combined > 40) {
            return response()->json([
                'error' => 'watchlist_full',
                'message' => 'Combined services + urls exceeds 40',
            ], 422);
        }

        $user->save();

        return response()->json([
            'services' => (array) $user->ooni_watchlist,
            'urls' => (array) $user->ooni_watchlist_urls,
        ]);
    }
}
