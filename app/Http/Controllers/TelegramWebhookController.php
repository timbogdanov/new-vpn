<?php

namespace App\Http\Controllers;

use App\Services\XuiService;
use App\Telegram\Handlers\CallbackQueryHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

class TelegramWebhookController extends Controller
{
    private Api $telegram;

    public function __construct()
    {
        $this->telegram = new Api(config('telegram.bot_token'));
    }

    public function handle(Request $request): Response
    {
        try {
            $update = new Update($request->all());

            Log::debug('Telegram webhook received', [
                'update_id' => $update->getUpdateId(),
                'type' => $this->getUpdateType($update)
            ]);

            // Handle callback queries (button presses)
            if ($update->has('callback_query')) {
                $handler = app(CallbackQueryHandler::class, ['telegram' => $this->telegram]);
                $handler->handle($update);
                return response('OK', 200);
            }

            // Handle messages
            if ($update->has('message')) {
                $message = $update->getMessage();

                // Handle /start command
                if ($message->has('text')) {
                    $text = $message->getText();

                    if (str_starts_with($text, '/start')) {
                        // Check for deep link parameters
                        $parts = explode(' ', $text, 2);
                        $param = $parts[1] ?? null;

                        if ($param && str_starts_with($param, 'ipcheck_')) {
                            $this->handleIpCheckResult($update, $param);
                        } else {
                            $this->handleStartCommand($update);
                        }
                        return response('OK', 200);
                    }
                }
            }

            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error('Telegram webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return 200 to prevent Telegram from retrying
            return response('OK', 200);
        }
    }

    private function getUpdateType(Update $update): string
    {
        if ($update->has('callback_query')) return 'callback_query';
        if ($update->has('message')) return 'message';
        if ($update->has('edited_message')) return 'edited_message';
        return 'unknown';
    }

    private function handleStartCommand(Update $update): void
    {
        $message = $update->getMessage();
        $telegramId = $message->getFrom()->getId();
        $firstName = $message->getFrom()->getFirstName();
        $lastName = $message->getFrom()->getLastName();
        $chatId = $message->getChat()->getId();

        // Get user language preference
        $language = Cache::get("lang_{$telegramId}", 'ru');
        App::setLocale($language);

        try {
            // Get or create VPN client
            $xuiService = app(XuiService::class);
            $client = $xuiService->getOrCreateClient($telegramId, $firstName, $lastName);

            Log::info('User started bot', [
                'telegramId' => $telegramId,
                'firstName' => $firstName,
                'clientEmail' => $client->email
            ]);

            // Send welcome message with menu
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $this->getWelcomeMessage($firstName),
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => $this->getMainMenuKeyboard()
                ])
            ]);

        } catch (\Exception $e) {
            Log::error('StartCommand failed', [
                'telegramId' => $telegramId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => __('menu.error_occurred'),
                'parse_mode' => 'HTML'
            ]);
        }
    }

    private function handleIpCheckResult(Update $update, string $param): void
    {
        $message = $update->getMessage();
        $chatId = $message->getChat()->getId();
        $telegramId = $message->getFrom()->getId();

        // Set user language
        $language = Cache::get("lang_{$telegramId}", 'ru');
        App::setLocale($language);

        // Parse param: ipcheck_{uid}_{token}
        $parts = explode('_', $param);
        if (count($parts) < 3) {
            $this->sendIpCheckError($chatId);
            return;
        }

        $uid = $parts[1];
        $token = $parts[2];

        // Handle error token
        if ($token === 'error') {
            $this->sendIpCheckError($chatId);
            return;
        }

        // Get cached result
        $result = Cache::get("ip_check_{$uid}_{$token}");

        if (!$result) {
            $this->sendIpCheckError($chatId, true);
            return;
        }

        // Build response message
        if ($result->isProtected) {
            $statusIcon = "\u{2705}"; // ✅
            $statusText = __('ip_check.protected');
            $statusDesc = __('ip_check.protected_desc');
        } else {
            $statusIcon = "\u{26A0}\u{FE0F}"; // ⚠️
            $statusText = __('ip_check.not_protected');
            $statusDesc = __('ip_check.not_protected_desc');
        }

        $text = "<b>" . __('ip_check.title') . "</b>\n\n";
        $text .= __('ip_check.ip') . ": <code>{$result->getMaskedIp()}</code>\n";
        $text .= __('ip_check.location') . ": {$result->city}, {$result->country} {$result->getFlag()}\n";
        $text .= __('ip_check.isp') . ": {$result->isp}\n\n";
        $text .= "{$statusIcon} <b>{$statusText}</b>\n";
        $text .= $statusDesc;

        $keyboard = [
            [['text' => __('menu.back'), 'callback_data' => 'back_to_menu']]
        ];

        // Add "Connect Now" button if not protected
        if (!$result->isProtected) {
            $keyboard = [
                [['text' => __('ip_check.connect_now'), 'callback_data' => 'choose_device']],
                [['text' => __('menu.back'), 'callback_data' => 'back_to_menu']]
            ];
        }

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    private function sendIpCheckError(int $chatId, bool $expired = false): void
    {
        $text = $expired ? __('ip_check.expired') : __('ip_check.error');

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => __('ip_check.try_again'), 'callback_data' => 'check_protection']],
                    [['text' => __('menu.back'), 'callback_data' => 'back_to_menu']]
                ]
            ])
        ]);
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
                ['text' => __('menu.check_protection'), 'callback_data' => 'check_protection'],
            ],
            [
                ['text' => __('menu.speed_test'), 'callback_data' => 'speed_test'],
                ['text' => __('menu.language'), 'callback_data' => 'select_language'],
            ]
        ];
    }
}
