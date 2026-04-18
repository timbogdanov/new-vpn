<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:set-webhook {--url= : The webhook URL}';
    protected $description = 'Set the Telegram bot webhook URL';

    public function handle(): int
    {
        $token = config('telegram.bot_token');

        if (!$token) {
            $this->error('TELEGRAM_BOT_TOKEN is not set in .env');
            return 1;
        }

        $url = $this->option('url') ?? config('app.url') . '/telegram/webhook';

        $payload = [
            'url' => $url,
            // pre_checkout_query is required for Telegram Stars; Telegram does
            // NOT include it in defaults so it must be opted into explicitly.
            'allowed_updates' => ['message', 'callback_query', 'pre_checkout_query'],
        ];

        $secret = (string) config('telegram.webhook_secret_token', '');
        if ($secret !== '') {
            $payload['secret_token'] = $secret;
        }

        $response = Http::post("https://api.telegram.org/bot{$token}/setWebhook", $payload);

        if ($response->successful() && $response->json('ok')) {
            $this->info("Webhook set successfully: {$url}");
            return 0;
        }

        $this->error('Failed to set webhook: ' . $response->json('description'));
        return 1;
    }
}
