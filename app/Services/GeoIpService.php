<?php

namespace App\Services;

use App\DTO\IpCheckResultDTO;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeoIpService
{
    // VPN server location - used to determine if user is protected
    private const VPN_COUNTRY_CODE = 'US';
    private const VPN_ISP = 'Hetzner';

    public function lookup(string $ip): ?IpCheckResultDTO
    {
        // If the IP is private/internal, the user is connected to VPN
        // and routing through the Docker network - they ARE protected
        if ($this->isPrivateIp($ip)) {
            Log::info('GeoIP: Private IP detected - user is on VPN', ['ip' => $ip]);
            return new IpCheckResultDTO(
                ip: 'VPN',
                city: 'Hillsboro',
                country: 'United States',
                countryCode: 'US',
                isp: 'Hetzner Online',
                isProtected: true,
                checkedAt: Carbon::now()
            );
        }

        try {
            // Use ip-api.com (free, no API key needed, 45 req/min limit)
            $response = Http::timeout(5)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'status,message,country,countryCode,city,isp,query'
            ]);

            if (!$response->successful()) {
                Log::error('GeoIP: Request failed', ['ip' => $ip, 'status' => $response->status()]);
                return null;
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'success') {
                Log::error('GeoIP: Lookup failed', ['ip' => $ip, 'message' => $data['message'] ?? 'Unknown']);
                return null;
            }

            $isProtected = $this->checkIfProtected($data['countryCode'] ?? '', $data['isp'] ?? '');

            return new IpCheckResultDTO(
                ip: $data['query'] ?? $ip,
                city: $data['city'] ?? 'Unknown',
                country: $data['country'] ?? 'Unknown',
                countryCode: $data['countryCode'] ?? '',
                isp: $data['isp'] ?? 'Unknown',
                isProtected: $isProtected,
                checkedAt: Carbon::now()
            );

        } catch (\Exception $e) {
            Log::error('GeoIP: Exception', ['ip' => $ip, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function checkIfProtected(string $countryCode, string $isp): bool
    {
        // User is protected if they appear from VPN server location
        $matchesCountry = strtoupper($countryCode) === self::VPN_COUNTRY_CODE;
        $matchesIsp = stripos($isp, self::VPN_ISP) !== false;

        // Must match both country AND ISP to be considered protected
        return $matchesCountry && $matchesIsp;
    }

    private function isPrivateIp(string $ip): bool
    {
        // Check if IP is in private ranges (RFC 1918) or Docker networks
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
