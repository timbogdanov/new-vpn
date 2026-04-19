<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;

/**
 * Thin wrapper around Telegram Bot API sendMessage for outbound/proactive
 * pushes (payment confirmations, OONI watchlist alerts, etc). Mirrors the
 * private reply() helper in TelegramWebhookController but is injectable
 * from any service / command.
 */
class TelegramMessageService
{
    private Api $telegram;

    public function __construct()
    {
        $this->telegram = new Api((string) config('telegram.bot_token'));
    }

    /**
     * Send a plain-HTML message to a chat. Optional inline keyboard.
     * Returns true on success, false on any failure (logged). Never throws.
     *
     * @param  array<int, array<int, array{text:string, url?:string, callback_data?:string}>>  $inlineKeyboard
     */
    public function send(int $chatId, string $text, array $inlineKeyboard = []): bool
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];
        if ($inlineKeyboard) {
            $payload['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
        }
        try {
            $this->telegram->sendMessage($payload);
            return true;
        } catch (\Throwable $e) {
            Log::warning('TelegramMessageService: send failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
