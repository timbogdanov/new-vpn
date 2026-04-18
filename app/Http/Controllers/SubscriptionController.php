<?php

namespace App\Http\Controllers;

use App\Models\TelegramUser;
use App\Services\AggregatedSubscriptionService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionController extends Controller
{
    public function show(Request $request, string $token, AggregatedSubscriptionService $aggregated): Response
    {
        if (strlen($token) < 16 || strlen($token) > 64) {
            abort(404);
        }

        $user = TelegramUser::where('sub_token', $token)->first();
        if (!$user) {
            abort(404);
        }

        $body = $aggregated->buildFor($user);

        return response($body, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Cache-Control', 'no-store, must-revalidate')
            ->header('Profile-Update-Interval', '24')
            ->header('Profile-Title', 'base64:' . base64_encode('Larastory VPN'));
    }
}
