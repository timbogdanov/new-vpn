<?php

namespace App\Http\Controllers\MiniApp;

use App\Http\Controllers\Controller;
use App\Services\GeoIpService;
use App\Services\OoniService;
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
        // Allow the client to override country/ASN via query (for VPN-on
        // retries and explicit "what's blocked in X" browsing). Default
        // comes from server-side geo of the current connection.
        $qCountry = strtoupper((string) $request->query('country', ''));
        $qAsn = strtoupper((string) $request->query('asn', ''));

        $country = preg_match('/^[A-Z]{2}$/', $qCountry) ? $qCountry : null;
        $asn = preg_match('/^AS\d+$/', $qAsn) ? $qAsn : null;
        $asnName = null;

        if (!$country || !$asn) {
            $geoResult = $geo->lookup($this->resolveClientIp($request));
            if ($geoResult) {
                $country = $country ?: $geoResult->countryCode;
                $asn = $asn ?: $geoResult->asn;
                $asnName = $geoResult->asnName;
            }
        }

        if (!$country) {
            return response()->json([
                'error' => 'geo_failed',
                'message' => 'Could not detect your network',
            ], 502);
        }

        $summary = $ooni->summary($country, $asn);
        $payload = $summary->toArray();
        $payload['asnName'] = $asnName ?: $payload['asnName'];

        return response()->json(['result' => $payload]);
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
