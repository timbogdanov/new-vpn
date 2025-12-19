<?php

namespace App\Telegram\Commands;

use App\Services\XuiService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Commands\Command;

class StartCommand extends Command
{
    protected string $name = 'start';
    protected string $description = 'Start the bot and get VPN access';

    public function handle(): void
    {
        $telegramId = $this->getUpdate()->getMessage()->getFrom()->getId();
        $firstName = $this->getUpdate()->getMessage()->getFrom()->getFirstName();

        // Get user language preference
        $language = Cache::get("lang_{$telegramId}", 'ru');
        App::setLocale($language);

        try {
            // Get or create VPN client
            $xuiService = app(XuiService::class);
            $client = $xuiService->getOrCreateClient($telegramId);

            Log::info('User started bot', [
                'telegramId' => $telegramId,
                'firstName' => $firstName,
                'clientEmail' => $client->email
            ]);

            // Send welcome message with menu
            $this->replyWithMessage([
                'text' => $this->getWelcomeMessage($firstName),
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => $this->getMainMenuKeyboard()
                ])
            ]);

        } catch (\Exception $e) {
            Log::error('StartCommand failed', [
                'telegramId' => $telegramId,
                'error' => $e->getMessage()
            ]);

            $this->replyWithMessage([
                'text' => __('menu.error_occurred'),
                'parse_mode' => 'HTML'
            ]);
        }
    }

    private function getWelcomeMessage(string $firstName): string
    {
        return __('menu.welcome', ['name' => $firstName]) . "\n\n" .
               __('menu.welcome_description');
    }

    private function getMainMenuKeyboard(): array
    {
        return [
            [
                ['text' => __('menu.connect'), 'callback_data' => 'choose_device'],
                ['text' => __('menu.profile'), 'callback_data' => 'profile'],
            ],
            [
                ['text' => __('menu.language'), 'callback_data' => 'select_language'],
            ]
        ];
    }
}
