<?php

namespace App\Http\Controllers;

use App\Services\GeoIpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IpCheckController extends Controller
{
    public function check(Request $request, GeoIpService $geoIpService)
    {
        $uid = $request->query('uid');

        if (!$uid) {
            return response('Missing user ID', 400);
        }

        // Get the real IP address
        $ip = $this->getClientIp($request);

        Log::info('IP Check requested', ['uid' => $uid, 'ip' => $ip]);

        // Lookup geo information
        $result = $geoIpService->lookup($ip);

        if (!$result) {
            // If lookup fails, redirect back with error token
            return $this->redirectToBot($uid, 'error');
        }

        // Generate a unique token for this check
        $token = Str::random(8);

        // Cache the result for 60 seconds
        Cache::put("ip_check_{$uid}_{$token}", $result, 60);

        Log::info('IP Check completed', [
            'uid' => $uid,
            'ip' => $result->getMaskedIp(),
            'country' => $result->countryCode,
            'protected' => $result->isProtected
        ]);

        // Redirect back to Telegram bot
        return $this->redirectToBot($uid, $token);
    }

    private function getClientIp(Request $request): string
    {
        // Check for forwarded headers (behind proxy/load balancer)
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Standard proxy
            'HTTP_X_REAL_IP',            // Nginx
            'REMOTE_ADDR',               // Direct connection
        ];

        foreach ($headers as $header) {
            $ip = $request->server($header);
            if ($ip) {
                // X-Forwarded-For can contain multiple IPs, get the first one
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $request->ip() ?? '0.0.0.0';
    }

    private function redirectToBot(string $uid, string $token): \Illuminate\Http\RedirectResponse
    {
        $botUsername = config('telegram.bot_username');

        if (!$botUsername) {
            Log::error('IP Check: Bot username not configured');
            return redirect()->away('https://t.me');
        }

        $deepLink = "https://t.me/{$botUsername}?start=ipcheck_{$uid}_{$token}";

        return redirect()->away($deepLink);
    }
}
