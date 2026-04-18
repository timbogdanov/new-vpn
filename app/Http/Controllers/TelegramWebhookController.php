<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\TelegramUser;
use App\Services\SubscriptionService;
use App\Services\TelegramAuthService;
use App\Services\TelegramBillingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

class TelegramWebhookController extends Controller
{
    private Api $telegram;

    public function __construct(
        private readonly TelegramAuthService $auth,
        private readonly TelegramBillingService $billing,
        private readonly SubscriptionService $subscriptions,
    ) {
        $this->telegram = new Api((string) config('telegram.bot_token'));
    }

    public function handle(Request $request): Response
    {
        $expectedSecret = (string) config('telegram.webhook_secret_token', '');
        if ($expectedSecret !== '') {
            $received = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
            if (!hash_equals($expectedSecret, $received)) {
                Log::warning('Telegram webhook secret token mismatch', [
                    'ip' => $request->ip(),
                ]);
                return response('OK', 200);
            }
        }

        try {
            $update = new Update($request->all());

            // Stars: pre_checkout_query — 10s deadline. No DB-heavy work here.
            if ($update->has('pre_checkout_query')) {
                $this->handlePreCheckout($update);
                return response('OK', 200);
            }

            if ($update->has('message')) {
                $message = $update->getMessage();

                // Stars: successful_payment lands inside a message. Handle first
                // so the "open app" reply doesn't fire on payment confirmations.
                if ($message->has('successful_payment')) {
                    $this->handleSuccessfulPayment($update);
                    return response('OK', 200);
                }

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

    private function handlePreCheckout(Update $update): void
    {
        $pcq = $update->get('pre_checkout_query');
        $queryId = (string) ($pcq['id'] ?? '');
        $payload = (string) ($pcq['invoice_payload'] ?? '');
        $totalAmount = (int) ($pcq['total_amount'] ?? 0);

        if ($queryId === '' || $payload === '') {
            return;
        }

        $payment = Payment::where('invoice_payload', $payload)
            ->where('status', Payment::STATUS_PENDING)
            ->first();

        if (!$payment) {
            $this->billing->answerPreCheckoutQuery(
                $queryId,
                false,
                __('billing.errors.unknown_invoice'),
            );
            return;
        }

        if ($totalAmount > 0 && $totalAmount !== $payment->stars_amount) {
            $this->billing->answerPreCheckoutQuery(
                $queryId,
                false,
                __('billing.errors.payment_failed'),
            );
            return;
        }

        $this->billing->answerPreCheckoutQuery($queryId, true);
    }

    private function handleSuccessfulPayment(Update $update): void
    {
        $message = $update->getMessage();
        $sp = $message->get('successful_payment');
        if (!is_array($sp)) {
            return;
        }

        $payload = (string) ($sp['invoice_payload'] ?? '');
        $chargeId = (string) ($sp['telegram_payment_charge_id'] ?? '');
        $providerChargeId = (string) ($sp['provider_payment_charge_id'] ?? '');
        $totalAmount = (int) ($sp['total_amount'] ?? 0);

        if ($payload === '' || $chargeId === '') {
            return;
        }

        // Idempotency: if we've already processed this charge_id, ignore retries.
        $existing = Payment::where('telegram_payment_charge_id', $chargeId)->first();
        if ($existing && $existing->status === Payment::STATUS_PAID) {
            return;
        }

        $payment = Payment::where('invoice_payload', $payload)->first();
        if (!$payment) {
            Log::warning('Stars: successful_payment with unknown payload', [
                'payload' => $payload,
                'charge_id' => $chargeId,
            ]);
            return;
        }

        $user = TelegramUser::find($payment->telegram_user_id);
        if (!$user) {
            Log::error('Stars: payment user missing', [
                'payment_id' => $payment->id,
                'telegram_user_id' => $payment->telegram_user_id,
            ]);
            return;
        }

        try {
            DB::transaction(function () use ($payment, $chargeId, $providerChargeId, $sp) {
                $payment->forceFill([
                    'telegram_payment_charge_id' => $chargeId,
                    'provider_payment_charge_id' => $providerChargeId ?: null,
                    'raw_payload' => $sp,
                ])->save();
            });

            $sub = $this->subscriptions->activate($user, $payment->plan_key, $payment->fresh());

            $this->sendPaymentConfirmation($user, $sub);
        } catch (\Throwable $e) {
            Log::error('Stars: activation failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendPaymentConfirmation(TelegramUser $user, Subscription $sub): void
    {
        $locale = $user->language_code ?: 'ru';
        App::setLocale($locale);

        $planName = (string) Lang::get(
            'billing.plans.' . $sub->plan_key . '.name',
            [],
            $locale,
        );
        $text = __('billing.success.activated', ['plan' => $planName]);

        try {
            $this->telegram->sendMessage([
                'chat_id' => $user->telegram_id,
                'text' => $text,
                'reply_markup' => json_encode(['inline_keyboard' => $this->openAppKeyboard()]),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Stars: confirmation send failed', [
                'telegram_id' => $user->telegram_id,
                'error' => $e->getMessage(),
            ]);
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
        $appName = config('app.name', 'Konnekt VPN');
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
