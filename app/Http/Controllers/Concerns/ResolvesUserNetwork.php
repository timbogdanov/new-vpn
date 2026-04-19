<?php

namespace App\Http\Controllers\Concerns;

use App\Services\GeoIpService;
use Illuminate\Http\Request;

/**
 * Shared logic for resolving the current user's (country, asn) tuple across
 * OONI endpoints. Priority: explicit query params → server-side GeoIP lookup
 * on the connecting IP. Also persists `ooni_last_country` / `ooni_last_asn`
 * on the authenticated user so the watchlist scheduler knows where to query.
 */
trait ResolvesUserNetwork
{
    /**
     * @return array{country: ?string, asn: ?string, asnName: ?string}
     */
    protected function resolveNetwork(Request $request, GeoIpService $geo): array
    {
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

        $user = $request->user();
        if ($user && $country) {
            $dirty = false;
            if ($user->ooni_last_country !== $country) {
                $user->ooni_last_country = $country;
                $dirty = true;
            }
            if ($asn && $user->ooni_last_asn !== $asn) {
                $user->ooni_last_asn = $asn;
                $dirty = true;
            }
            if ($dirty) {
                $user->save();
            }
        }

        return ['country' => $country, 'asn' => $asn, 'asnName' => $asnName];
    }

    protected function resolveClientIp(Request $request): string
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
