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
        // Log all relevant headers for debugging
        Log::debug('IP Check headers', [
            'X-Forwarded-For' => $request->header('X-Forwarded-For'),
            'X-Real-IP' => $request->header('X-Real-IP'),
            'CF-Connecting-IP' => $request->header('CF-Connecting-IP'),
            'request_ip' => $request->ip(),
        ]);

        // Check for forwarded headers (behind proxy/load balancer)
        // Try headers in order of preference
        $forwardedFor = $request->header('X-Forwarded-For');
        if ($forwardedFor) {
            // X-Forwarded-For can contain multiple IPs, get the first (original client)
            $ip = trim(explode(',', $forwardedFor)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        $realIp = $request->header('X-Real-IP');
        if ($realIp && filter_var($realIp, FILTER_VALIDATE_IP)) {
            return $realIp;
        }

        $cfIp = $request->header('CF-Connecting-IP');
        if ($cfIp && filter_var($cfIp, FILTER_VALIDATE_IP)) {
            return $cfIp;
        }

        // Fallback to Laravel's detected IP
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
