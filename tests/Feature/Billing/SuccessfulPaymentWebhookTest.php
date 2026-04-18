<?php

namespace Tests\Feature\Billing;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\TelegramUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SuccessfulPaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('telegram.bot_token', 'test_bot_token');
        config()->set('telegram.webhook_secret_token', '');
        Http::preventStrayRequests();
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);
    }

    public function test_successful_payment_activates_subscription(): void
    {
        $user = TelegramUser::create([
            'telegram_id' => 88001, 'first_name' => 'A', 'language_code' => 'ru',
        ]);
        $payment = Payment::create([
            'telegram_user_id' => $user->telegram_id,
            'plan_key' => 'pro_monthly',
            'stars_amount' => 150,
            'currency' => 'XTR',
            'invoice_payload' => 'sub_88001_pro_monthly_abc123',
            'status' => Payment::STATUS_PENDING,
        ]);

        $this->postJson('/telegram/webhook', $this->successfulPaymentUpdate(
            chatId: 88001,
            payload: 'sub_88001_pro_monthly_abc123',
            chargeId: 'tg_charge_abc',
            stars: 150,
        ))->assertOk();

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame('tg_charge_abc', $payment->telegram_payment_charge_id);

        $sub = Subscription::forUser(88001)->active()->first();
        $this->assertNotNull($sub);
        $this->assertSame(Subscription::TIER_PRO, $sub->tier);
        $this->assertSame(150, $sub->stars_paid);
    }

    public function test_replay_with_same_charge_id_does_not_double_activate(): void
    {
        $user = TelegramUser::create([
            'telegram_id' => 88002, 'first_name' => 'B', 'language_code' => 'ru',
        ]);
        Payment::create([
            'telegram_user_id' => $user->telegram_id,
            'plan_key' => 'pro_monthly',
            'stars_amount' => 150,
            'currency' => 'XTR',
            'invoice_payload' => 'sub_88002_pro_monthly_xyz',
            'status' => Payment::STATUS_PENDING,
        ]);

        $update = $this->successfulPaymentUpdate(
            chatId: 88002,
            payload: 'sub_88002_pro_monthly_xyz',
            chargeId: 'tg_charge_replay',
            stars: 150,
        );

        $this->postJson('/telegram/webhook', $update)->assertOk();
        $this->postJson('/telegram/webhook', $update)->assertOk();
        $this->postJson('/telegram/webhook', $update)->assertOk();

        $this->assertSame(1, Subscription::forUser(88002)->where('status', Subscription::STATUS_ACTIVE)->count());
    }

    public function test_unknown_payload_is_ignored(): void
    {
        $this->postJson('/telegram/webhook', $this->successfulPaymentUpdate(
            chatId: 88003,
            payload: 'mystery_payload',
            chargeId: 'tg_charge_unknown',
            stars: 150,
        ))->assertOk();

        $this->assertSame(0, Subscription::count());
    }

    public function test_secret_token_mismatch_rejects_payload(): void
    {
        config()->set('telegram.webhook_secret_token', 'expected-secret');

        $user = TelegramUser::create([
            'telegram_id' => 88004, 'first_name' => 'D', 'language_code' => 'ru',
        ]);
        Payment::create([
            'telegram_user_id' => $user->telegram_id,
            'plan_key' => 'pro_monthly',
            'stars_amount' => 150,
            'currency' => 'XTR',
            'invoice_payload' => 'sub_88004_pro_monthly_secret',
            'status' => Payment::STATUS_PENDING,
        ]);

        // Wrong secret → 200 OK (suppress retry storm) but no activation.
        $this->postJson('/telegram/webhook', $this->successfulPaymentUpdate(
            chatId: 88004,
            payload: 'sub_88004_pro_monthly_secret',
            chargeId: 'tg_charge_blocked',
            stars: 150,
        ), ['X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret'])->assertOk();

        $this->assertSame(0, Subscription::forUser(88004)->where('status', Subscription::STATUS_ACTIVE)->count());

        // Correct secret → activation proceeds.
        $this->postJson('/telegram/webhook', $this->successfulPaymentUpdate(
            chatId: 88004,
            payload: 'sub_88004_pro_monthly_secret',
            chargeId: 'tg_charge_blocked',
            stars: 150,
        ), ['X-Telegram-Bot-Api-Secret-Token' => 'expected-secret'])->assertOk();

        $this->assertSame(1, Subscription::forUser(88004)->where('status', Subscription::STATUS_ACTIVE)->count());
    }

    private function successfulPaymentUpdate(int $chatId, string $payload, string $chargeId, int $stars): array
    {
        return [
            'update_id' => random_int(1, 999_999_999),
            'message' => [
                'message_id' => 1,
                'date' => time(),
                'chat' => ['id' => $chatId, 'type' => 'private'],
                'from' => ['id' => $chatId, 'is_bot' => false, 'first_name' => 'X'],
                'successful_payment' => [
                    'currency' => 'XTR',
                    'total_amount' => $stars,
                    'invoice_payload' => $payload,
                    'telegram_payment_charge_id' => $chargeId,
                    'provider_payment_charge_id' => '',
                ],
            ],
        ];
    }
}
