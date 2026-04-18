<?php

use App\Http\Controllers\MiniAppController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\VpnRedirectController;
use Illuminate\Support\Facades\Route;

// Health check for Docker / load balancer
Route::get('/health', function () {
    return response('healthy', 200)->header('Content-Type', 'text/plain');
});

// Mini App shell (Telegram WebApp URL target)
Route::get('/app', [MiniAppController::class, 'show'])->name('miniapp');

// Aggregated subscription endpoint — one URL, all of a user's servers
Route::get('/sub/u/{token}', [SubscriptionController::class, 'show'])
    ->middleware('throttle:30,1')
    ->where('token', '[A-Za-z0-9]{16,64}');

// HTTPS fallback that redirects into the user's VPN app deep-link scheme
Route::get('/vpn-link', [VpnRedirectController::class, 'redirect']);

// Telegram bot webhook
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'status' => 'running',
    ]);
});
