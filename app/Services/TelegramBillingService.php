<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TelegramBillingService
{
    /**
     * Create a Stars invoice link via Bot API.
     *
     * Pure HTTP — does not touch our DB. Caller is responsible for persisting
     * the pending Payment row that the webhook will resolve later.
     *
     * @return string The t.me/$/invoice/<slug> link to pass to openInvoice().
     */
    public function createInvoiceLink(
        string $title,
        string $description,
        string $payload,
        int $stars,
    ): string {
        $token = (string) config('telegram.bot_token');
        if ($token === '') {
            throw new RuntimeException('telegram bot token not configured');
        }

        $body = [
            'title' => $title,
            'description' => $description,
            'payload' => $payload,
            'currency' => (string) config('telegram.stars_currency', 'XTR'),
            'prices' => json_encode([
                ['label' => $title, 'amount' => $stars],
            ]),
            // Stars invoices use an empty provider_token by spec.
            'provider_token' => '',
        ];

        $response = Http::asForm()
            ->timeout(8)
            ->post("https://api.telegram.org/bot{$token}/createInvoiceLink", $body);

        if (!$response->successful() || !$response->json('ok')) {
            Log::error('TelegramBillingService: createInvoiceLink failed', [
                'payload' => $payload,
                'response' => $response->json(),
            ]);
            throw new RuntimeException('Failed to create invoice link');
        }

        return (string) $response->json('result');
    }

    /**
     * Refund a Stars charge. Used by the admin panel refund action.
     */
    public function refundStarPayment(int $userId, string $telegramPaymentChargeId): bool
    {
        $token = (string) config('telegram.bot_token');
        if ($token === '') {
            throw new RuntimeException('telegram bot token not configured');
        }

        $response = Http::asForm()
            ->timeout(10)
            ->post("https://api.telegram.org/bot{$token}/refundStarPayment", [
                'user_id' => $userId,
                'telegram_payment_charge_id' => $telegramPaymentChargeId,
            ]);

        if (!$response->successful() || !$response->json('ok')) {
            Log::error('TelegramBillingService: refundStarPayment failed', [
                'user_id' => $userId,
                'charge_id' => $telegramPaymentChargeId,
                'response' => $response->json(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Acknowledge a pre_checkout_query within Telegram's 10s deadline.
     */
    public function answerPreCheckoutQuery(string $queryId, bool $ok, ?string $errorMessage = null): void
    {
        $token = (string) config('telegram.bot_token');
        if ($token === '') {
            return;
        }

        $body = [
            'pre_checkout_query_id' => $queryId,
            'ok' => $ok,
        ];
        if (!$ok && $errorMessage) {
            $body['error_message'] = $errorMessage;
        }

        try {
            Http::asForm()
                ->timeout(8)
                ->post("https://api.telegram.org/bot{$token}/answerPreCheckoutQuery", $body);
        } catch (\Throwable $e) {
            Log::error('TelegramBillingService: answerPreCheckoutQuery failed', [
                'query_id' => $queryId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
