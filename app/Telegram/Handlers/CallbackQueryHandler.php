<?php

namespace App\Telegram\Handlers;

use App\Services\LinkService;
use App\Services\XuiService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

class CallbackQueryHandler
{
    private Api $telegram;
    private XuiService $xuiService;
    private LinkService $linkService;

    public function __construct(Api $telegram, XuiService $xuiService, LinkService $linkService)
    {
        $this->telegram = $telegram;
        $this->xuiService = $xuiService;
        $this->linkService = $linkService;
    }

    public function handle(Update $update): void
    {
        $callbackQuery = $update->getCallbackQuery();
        $data = $callbackQuery->getData();
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $messageId = $callbackQuery->getMessage()->getMessageId();
        $telegramId = $callbackQuery->getFrom()->getId();

        // Debounce protection
        $cacheKey = "callback_{$telegramId}_{$data}";
        if (Cache::has($cacheKey)) {
            $this->answerCallback($callbackQuery->getId());
            return;
        }
        Cache::put($cacheKey, true, 3);

        // Set user language
        $language = Cache::get("lang_{$telegramId}", 'ru');
        App::setLocale($language);

        try {
            match ($data) {
                'choose_device' => $this->handleChooseDevice($chatId, $messageId),
                'device_apple' => $this->handleDevice($chatId, $messageId, $telegramId, 'apple'),
                'device_android' => $this->handleDevice($chatId, $messageId, $telegramId, 'android'),
                'device_windows' => $this->handleDevice($chatId, $messageId, $telegramId, 'windows'),
                'show_vless_link' => $this->handleShowVlessLink($chatId, $telegramId),
                'profile' => $this->handleProfile($chatId, $messageId, $telegramId),
                'select_language' => $this->handleSelectLanguage($chatId, $messageId),
                'set_language_ru' => $this->handleSetLanguage($chatId, $messageId, $telegramId, 'ru'),
                'set_language_en' => $this->handleSetLanguage($chatId, $messageId, $telegramId, 'en'),
                'back_to_menu' => $this->handleBackToMenu($chatId, $messageId, $telegramId),
                'back_to_devices' => $this->handleChooseDevice($chatId, $messageId),
                default => Log::warning('Unknown callback data', ['data' => $data]),
            };

            $this->answerCallback($callbackQuery->getId());
        } catch (\Exception $e) {
            Log::error('Callback handler error', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            $this->answerCallback($callbackQuery->getId(), __('menu.error_occurred'));
        }
    }

    private function handleChooseDevice(int $chatId, int $messageId): void
    {
        $this->telegram->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => __('device.choose_title'),
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => __('device.apple'), 'callback_data' => 'device_apple'],
                    ],
                    [
                        ['text' => __('device.android'), 'callback_data' => 'device_android'],
                    ],
                    [
                        ['text' => __('device.windows'), 'callback_data' => 'device_windows'],
                    ],
                    [
                        ['text' => __('menu.back'), 'callback_data' => 'back_to_menu'],
                    ]
                ]
            ])
        ]);
    }

    private function handleDevice(int $chatId, int $messageId, int $telegramId, string $device): void
    {
        $client = $this->xuiService->getClientByTelegramId($telegramId);

        if (!$client) {
            $client = $this->xuiService->createClient($telegramId);
        }

        $links = $this->linkService->createLinks($client, $device);
        $appLinks = $this->linkService->getAppDownloadLinks();

        $deviceTitle = match ($device) {
            'apple' => __('device.apple_title'),
            'android' => __('device.android_title'),
            'windows' => __('device.windows_title'),
            default => __('device.apple_title'),
        };

        $message = "<b>{$deviceTitle}</b>\n\n";
        $message .= __('device.instructions') . "\n";
        $message .= "1. " . __('device.step1') . "\n";
        $message .= "2. " . __('device.step2') . "\n\n";
        $message .= __('device.verify_hint') . " <code>ip.me</code>";

        $keyboard = [
            [
                ['text' => __('device.download_app'), 'url' => $appLinks[$device]],
            ],
            [
                ['text' => __('device.auto_connect'), 'url' => $links['redirectUrl']],
            ],
            [
                ['text' => __('device.copy_vless_link'), 'callback_data' => 'show_vless_link'],
            ],
            [
                ['text' => __('device.back_to_devices'), 'callback_data' => 'back_to_devices'],
                ['text' => __('menu.back'), 'callback_data' => 'back_to_menu'],
            ]
        ];

        $this->telegram->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    private function handleShowVlessLink(int $chatId, int $telegramId): void
    {
        $client = $this->xuiService->getClientByTelegramId($telegramId);

        if (!$client) {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => __('profile.no_account'),
                'parse_mode' => 'HTML'
            ]);
            return;
        }

        $vlessLink = $this->xuiService->getVlessLink($client);

        if (!$vlessLink) {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => __('device.vless_link_error'),
                'parse_mode' => 'HTML'
            ]);
            return;
        }

        // Send link as standalone message for easy copying
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $vlessLink,
        ]);
    }

    private function handleProfile(int $chatId, int $messageId, int $telegramId): void
    {
        $client = $this->xuiService->getClientByTelegramId($telegramId);

        if (!$client) {
            $this->telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => __('profile.no_account'),
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [['text' => __('menu.back'), 'callback_data' => 'back_to_menu']]
                    ]
                ])
            ]);
            return;
        }

        $traffic = $this->xuiService->getClientTraffic($client->email);

        $statusText = $client->enabled ? __('profile.enabled') : __('profile.disabled');

        $message = "<b>" . __('profile.title') . "</b>\n\n";
        $message .= __('profile.status') . ": {$statusText}\n";
        $message .= __('profile.upload') . ": {$traffic->getFormattedUpload()}\n";
        $message .= __('profile.download') . ": {$traffic->getFormattedDownload()}\n";

        if ($client->hasUnlimitedExpiry()) {
            $message .= __('profile.expires') . ": " . __('profile.expires_never');
        } else {
            $expiryDate = date('d.m.Y', $client->expiryTime / 1000);
            $message .= __('profile.expires') . ": {$expiryDate}";
        }

        $this->telegram->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => __('menu.back'), 'callback_data' => 'back_to_menu']]
                ]
            ])
        ]);
    }

    private function handleSelectLanguage(int $chatId, int $messageId): void
    {
        $this->telegram->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => __('menu.select_language'),
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => 'Русский', 'callback_data' => 'set_language_ru'],
                        ['text' => 'English', 'callback_data' => 'set_language_en'],
                    ],
                    [
                        ['text' => __('menu.back'), 'callback_data' => 'back_to_menu'],
                    ]
                ]
            ])
        ]);
    }

    private function handleSetLanguage(int $chatId, int $messageId, int $telegramId, string $language): void
    {
        Cache::put("lang_{$telegramId}", $language, now()->addYear());
        App::setLocale($language);

        $this->handleBackToMenu($chatId, $messageId, $telegramId);
    }

    private function handleBackToMenu(int $chatId, int $messageId, int $telegramId): void
    {
        $this->telegram->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => __('menu.main_menu'),
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => __('menu.connect'), 'callback_data' => 'choose_device'],
                        ['text' => __('menu.profile'), 'callback_data' => 'profile'],
                    ],
                    [
                        ['text' => __('menu.language'), 'callback_data' => 'select_language'],
                    ]
                ]
            ])
        ]);
    }

    private function answerCallback(string $callbackQueryId, ?string $text = null): void
    {
        $params = ['callback_query_id' => $callbackQueryId];
        if ($text) {
            $params['text'] = $text;
            $params['show_alert'] = true;
        }
        $this->telegram->answerCallbackQuery($params);
    }
}
