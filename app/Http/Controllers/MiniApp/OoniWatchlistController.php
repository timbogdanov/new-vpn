<?php

namespace App\Http\Controllers\MiniApp;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OoniWatchlistController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $services = $user?->ooni_watchlist ?? [];
        return response()->json([
            'services' => array_values(array_unique($services)),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $validKeys = array_column((array) config('ooni.services', []), 'key');

        $data = $request->validate([
            'services' => ['present', 'array', 'max:20'],
            'services.*' => ['string', Rule::in($validKeys)],
        ]);

        $user->ooni_watchlist = array_values(array_unique($data['services'] ?? []));
        $user->save();

        return response()->json([
            'services' => $user->ooni_watchlist,
        ]);
    }
}
