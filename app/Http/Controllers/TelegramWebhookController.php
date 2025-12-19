<?php

namespace App\Http\Controllers;

use App\Telegram\Commands\StartCommand;
use App\Telegram\Handlers\CallbackQueryHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
                        $command = new StartCommand();
                        $command->setTelegram($this->telegram);
                        $command->setUpdate($update);
                        $command->handle();
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
}
