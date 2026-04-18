<?php

namespace App\Http\Controllers\MiniApp;

use App\Http\Controllers\Controller;
use App\Services\GeoIpService;
use App\Services\SpeedTestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ToolsController extends Controller
{
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

    private function resolveClientIp(Request $request): string
    {
        $forwardedFor = $request->header('X-Forwarded-For');
        if ($forwardedFor) {
            $first = trim(explode(',', $forwardedFor)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        foreach (['X-Real-IP', 'CF-Connecting-IP'] as $h) {
            $v = $request->header($h);
            if ($v && filter_var($v, FILTER_VALIDATE_IP)) {
                return $v;
            }
        }

        return $request->ip() ?? '0.0.0.0';
    }
}
