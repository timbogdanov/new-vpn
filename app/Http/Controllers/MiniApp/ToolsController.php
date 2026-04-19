<?php

namespace App\Http\Controllers\MiniApp;

use App\Http\Controllers\Concerns\ResolvesUserNetwork;
use App\Http\Controllers\Controller;
use App\Services\GeoIpService;
use App\Services\OoniService;
use App\Services\SpeedTestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ToolsController extends Controller
{
    use ResolvesUserNetwork;

    public function ipCheck(Request $request, GeoIpService $geo): JsonResponse
    {
        $ip = $this->resolveClientIp($request);
        $result = $geo->lookup($ip);

        if (!$result) {
            return response()->json([
                'error' => 'lookup_failed',
                'message' => 'Could not detect your IP right now',
            ], 502);
        }

        return response()->json([
            'result' => [
                'ip' => $result->getMaskedIp(),
                'city' => $result->city,
                'country' => $result->country,
                'countryCode' => $result->countryCode,
                'flag' => $result->getFlag(),
                'isp' => $result->isp,
                'isProtected' => $result->isProtected,
                'checkedAt' => $result->checkedAt->toIso8601String(),
                'asn' => $result->asn,
                'asnName' => $result->asnName,
            ],
        ]);
    }

    public function speedTest(Request $request, SpeedTestService $speed): JsonResponse
    {
        $result = $speed->runTest();

        if (!$result) {
            return response()->json([
                'error' => 'speedtest_failed',
                'message' => 'Could not run the speed test',
            ], 502);
        }

        return response()->json([
            'result' => [
                'downloadMbps' => round($result->downloadMbps, 1),
                'uploadMbps' => round($result->uploadMbps, 1),
                'pingMs' => round($result->pingMs, 0),
                'testedAt' => $result->testedAt->toIso8601String(),
            ],
        ]);
    }

    public function ooniSummary(Request $request, GeoIpService $geo, OoniService $ooni): JsonResponse
    {
        $net = $this->resolveNetwork($request, $geo);
        if (!$net['country']) {
            return response()->json([
                'error' => 'geo_failed',
                'message' => 'Could not detect your network',
            ], 502);
        }

        if ($request->boolean('force')) {
            $ooni->refresh($net['country'], $net['asn']);
        }
        $summary = $ooni->topBlocked($net['country'], $net['asn']);
        $payload = $summary->toArray();
        $payload['asnName'] = $net['asnName'] ?: $payload['asnName'];

        return response()->json(['result' => $payload]);
    }
}
