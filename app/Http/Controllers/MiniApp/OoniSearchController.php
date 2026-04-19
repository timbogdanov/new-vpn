<?php

namespace App\Http\Controllers\MiniApp;

use App\Http\Controllers\Concerns\ResolvesUserNetwork;
use App\Http\Controllers\Controller;
use App\Services\GeoIpService;
use App\Services\OoniSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OoniSearchController extends Controller
{
    use ResolvesUserNetwork;

    public function index(Request $request, GeoIpService $geo, OoniSearchService $search): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:64'],
            'country' => ['nullable', 'string', 'size:2'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $country = isset($data['country']) ? strtoupper($data['country']) : null;
        if (!$country) {
            $net = $this->resolveNetwork($request, $geo);
            $country = $net['country'];
        }

        $limit = (int) ($data['limit'] ?? 10);
        $results = $search->suggest($data['q'], $country, $limit);

        return response()->json(['results' => $results]);
    }
}
