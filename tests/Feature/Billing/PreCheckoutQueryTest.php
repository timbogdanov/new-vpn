<?php

namespace Tests\Feature\Billing;

use App\Models\Payment;
use App\Models\TelegramUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PreCheckoutQueryTest extends TestCase
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

    public function test_known_payload_answers_ok(): void
    {
        $user = TelegramUser::create([
            'telegram_id' => 77001, 'first_name' => 'A', 'language_code' => 'ru',
        ]);
        Payment::create([
            'telegram_user_id' => $user->telegram_id,
            'plan_key' => 'pro_monthly',
            'stars_amount' => 150,
            'currency' => 'XTR',
            'invoice_payload' => 'sub_77001_pro_monthly_pcq1',
            'status' => Payment::STATUS_PENDING,
        ]);

        $this->postJson('/telegram/webhook', $this->preCheckoutUpdate(
            queryId: 'pcq_1', payload: 'sub_77001_pro_monthly_pcq1', amount: 150,
        ))->assertOk();

        Http::assertSent(function ($req) {
            return str_contains($req->url(), '/answerPreCheckoutQuery')
                && $req['ok'] == 1;
        });
    }

    public function test_unknown_payload_answers_with_error(): void
    {
        $this->postJson('/telegram/webhook', $this->preCheckoutUpdate(
            queryId: 'pcq_2', payload: 'never_seen', amount: 150,
        ))->assertOk();

        Http::assertSent(function ($req) {
            return str_contains($req->url(), '/answerPreCheckoutQuery')
                && empty($req['ok']);
        });
    }

    public function test_amount_mismatch_rejects(): void
    {
        $user = TelegramUser::create([
            'telegram_id' => 77003, 'first_name' => 'C', 'language_code' => 'ru',
        ]);
        Payment::create([
            'telegram_user_id' => $user->telegram_id,
            'plan_key' => 'pro_monthly',
            'stars_amount' => 150,
            'currency' => 'XTR',
            'invoice_payload' => 'sub_77003_pro_monthly_pcq3',
            'status' => Payment::STATUS_PENDING,
        ]);

        $this->postJson('/telegram/webhook', $this->preCheckoutUpdate(
            queryId: 'pcq_3', payload: 'sub_77003_pro_monthly_pcq3', amount: 999,
        ))->assertOk();

        Http::assertSent(function ($req) {
            return str_contains($req->url(), '/answerPreCheckoutQuery')
                && empty($req['ok']);
        });
    }

    private function preCheckoutUpdate(string $queryId, string $payload, int $amount): array
    {
        return [
            'update_id' => random_int(1, 999_999_999),
            'pre_checkout_query' => [
                'id' => $queryId,
                'from' => ['id' => 77001, 'is_bot' => false, 'first_name' => 'X'],
                'currency' => 'XTR',
                'total_amount' => $amount,
                'invoice_payload' => $payload,
            ],
        ];
    }
}
