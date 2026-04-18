<?php

namespace App\Http\Controllers;

use App\Services\TelegramAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

class TelegramWebhookController extends Controller
{
    private Api $telegram;

    public function __construct(
        private readonly TelegramAuthService $auth,
    ) {
        $this->telegram = new Api((string) config('telegram.bot_token'));
    }

    public function handle(Request $request): Response
    {
        try {
            $update = new Update($request->all());

            if ($update->has('message')) {
                $message = $update->getMessage();
                $text = $message->has('text') ? (string) $message->getText() : '';

                if (str_starts_with($text, '/start')) {
                    $this->handleStart($update);
                } else {
                    $this->reply($message->getChat()->getId(), null, $this->openAppText(), $this->openAppKeyboard());
                }
            }

            // Callbacks from any lingering old messages: politely route them to the Mini App.
            if ($update->has('callback_query')) {
                $cb = $update->getCallbackQuery();
                try {
                    $this->telegram->answerCallbackQuery(['callback_query_id' => $cb->getId()]);
                } catch (\Throwable) {
                    // ignore — callback may be too old
                }
                $this->reply($cb->getMessage()->getChat()->getId(), null, $this->openAppText(), $this->openAppKeyboard());
            }

            return response('OK', 200);
        } catch (\Throwable $e) {
            Log::error('Telegram webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response('OK', 200);
        }
    }

    private function handleStart(Update $update): void
    {
        $message = $update->getMessage();
        $from = $message->getFrom();
        $chatId = $message->getChat()->getId();

        $user = $this->auth->upsertFromTelegramUpdate([
            'id' => (int) $from->getId(),
            'first_name' => $from->getFirstName(),
            'last_name' => $from->getLastName(),
            'username' => $from->getUsername(),
            'language_code' => $from->has('language_code') ? $from->getLanguageCode() : null,
            'is_premium' => $from->has('is_premium') ? (bool) $from->getIsPremium() : false,
        ]);

        App::setLocale($user->language_code ?: 'ru');

        Log::info('User started bot', [
            'telegramId' => $user->telegram_id,
            'firstName' => $user->first_name,
        ]);

        $this->reply(
            $chatId,
            $user->first_name,
            $this->openAppText($user->first_name),
            $this->openAppKeyboard(),
        );
    }

    private function reply(int $chatId, ?string $firstName, string $text, array $keyboard): void
    {
        try {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
            ]);
        } catch (\Throwable $e) {
            Log::error('Telegram sendMessage failed', ['error' => $e->getMessage()]);
        }
    }

    private function openAppText(?string $firstName = null): string
    {
        $appName = config('app.name', 'Larastory VPN');
        $greet = $firstName
            ? __('menu.welcome', ['name' => $firstName])
            : $appName;

        $body = __('menu.welcome_description');

        return "<b>{$greet}</b>\n\n{$body}";
    }

    private function openAppKeyboard(): array
    {
        $url = (string) config('miniapp.app_url');
        $text = __('menu.open_app');

        return [
            [[
                'text' => $text,
                'web_app' => ['url' => $url],
            ]],
        ];
    }
}
