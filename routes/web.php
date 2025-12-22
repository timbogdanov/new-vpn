<?php

use App\Http\Controllers\IpCheckController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\VpnRedirectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Health check for Docker/load balancer
Route::get('/health', function () {
    return response('healthy', 200)->header('Content-Type', 'text/plain');
});

// VPN redirect route - redirects to VPN app with subscription link
Route::get('/vpn-link', [VpnRedirectController::class, 'redirect']);

// IP check route - captures user IP and redirects back to bot
Route::get('/check-ip', [IpCheckController::class, 'check']);

// Telegram webhook
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);

// Home page - simple info
Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'status' => 'running'
    ]);
});
