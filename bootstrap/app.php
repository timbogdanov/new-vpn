<?php

use App\Http\Middleware\VerifyTelegramInitData;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Telegram webhook and the Mini App API are stateless — no CSRF.
        $middleware->validateCsrfTokens(except: [
            'telegram/*',
            'api/miniapp/*',
        ]);

        // Running behind Traefik / Nginx / Docker — forwarded headers are trusted.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'telegram.initdata' => VerifyTelegramInitData::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
