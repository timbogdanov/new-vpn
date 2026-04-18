<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SetTelegramMenuButton extends Command
{
    protected $signature = 'telegram:set-menu-button {--force : Skip the idempotency cache}';
    protected $description = 'Pin the Mini App as the chat menu button for the bot (global default scope)';

    public function handle(): int
    {
        $token = (string) config('telegram.bot_token');
        $appUrl = (string) config('miniapp.app_url');

        if ($token === '') {
            $this->error('TELEGRAM_BOT_TOKEN is not set');
            return 1;
        }
        if ($appUrl === '' || !str_starts_with($appUrl, 'https://')) {
            $this->error('MINIAPP_APP_URL must be an HTTPS URL (got: ' . $appUrl . ')');
            return 1;
        }

        $cacheKey = 'telegram_menu_button_set_' . md5($appUrl);

        if (!$this->option('force') && Cache::has($cacheKey)) {
            $this->info('Menu button already set for this APP_URL (cached). Use --force to re-send.');
            return 0;
        }

        $response = Http::post("https://api.telegram.org/bot{$token}/setChatMenuButton", [
            'menu_button' => [
                'type' => 'web_app',
                'text' => 'Open App',
                'web_app' => ['url' => $appUrl],
            ],
        ]);

        $ok = $response->successful() && $response->json('ok');

        if (!$ok) {
            $this->error('Telegram rejected setChatMenuButton: ' . $response->body());
            return 1;
        }

        Cache::put($cacheKey, true, now()->addDays(30));
        $this->info("Menu button pinned → {$appUrl}");
        return 0;
    }
}
