<?php

namespace App\Http\Middleware;

use App\Services\TelegramAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTelegramInitData
{
    public function __construct(
        private readonly TelegramAuthService $auth,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $initData = $request->header('X-Telegram-Init-Data') ?? '';

        // Dev/test fallback: accept `initData` in the body for easy local curling.
        if ($initData === '' && app()->environment('local', 'testing')) {
            $initData = (string) $request->input('_initData', '');
        }

        try {
            $user = $this->auth->validate($initData);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => $e->getMessage(),
            ], 401);
        }

        $user->touchActivityIfStale((int) config('miniapp.last_active_throttle_seconds', 60));

        $request->attributes->set('telegramUser', $user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
