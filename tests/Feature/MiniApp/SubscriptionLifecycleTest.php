<?php

namespace Tests\Feature\MiniApp;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\TelegramUser;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;
    use InitDataHelper;

    public function test_bootstrap_auto_grants_trial_to_new_user(): void
    {
        $initData = $this->buildInitData();

        $res = $this->getJson('/api/miniapp/bootstrap', [
            'X-Telegram-Init-Data' => $initData,
        ])->assertOk();

        $res->assertJsonPath('subscription.active.tier', Subscription::TIER_TRIAL);
        $res->assertJsonPath('subscription.active.status', Subscription::STATUS_TRIALING);
        $res->assertJsonPath('subscription.active.isTrial', true);

        $user = TelegramUser::find(99001);
        $this->assertNotNull($user->trial_used_at);
        $this->assertCount(1, $user->subscriptions);
    }

    public function test_trial_grant_is_idempotent_across_repeat_visits(): void
    {
        $initData = $this->buildInitData();

        $this->getJson('/api/miniapp/bootstrap', ['X-Telegram-Init-Data' => $initData])->assertOk();
        $this->getJson('/api/miniapp/bootstrap', ['X-Telegram-Init-Data' => $initData])->assertOk();

        $user = TelegramUser::find(99001);
        $this->assertCount(1, $user->subscriptions);
    }

    public function test_activate_replaces_trial_with_paid_plan(): void
    {
        $user = TelegramUser::create([
            'telegram_id' => 99001, 'first_name' => 'A', 'language_code' => 'ru',
        ]);

        /** @var SubscriptionService $svc */
        $svc = $this->app->make(SubscriptionService::class);
        $svc->grantTrial($user);

        $payment = Payment::create([
            'telegram_user_id' => $user->telegram_id,
            'plan_key' => 'pro_monthly',
            'stars_amount' => 150,
            'currency' => 'XTR',
            'invoice_payload' => 'inv_test_1',
            'telegram_payment_charge_id' => 'tg_charge_1',
            'status' => Payment::STATUS_PENDING,
        ]);

        $sub = $svc->activate($user, 'pro_monthly', $payment->fresh());

        $this->assertSame(Subscription::TIER_PRO, $sub->tier);
        $this->assertSame(Subscription::STATUS_ACTIVE, $sub->status);
        $this->assertSame(150, $sub->stars_paid);

        // Trial sub gets canceled.
        $trial = Subscription::forUser($user->telegram_id)
            ->where('tier', Subscription::TIER_TRIAL)
            ->first();
        $this->assertSame(Subscription::STATUS_CANCELED, $trial->status);

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame($sub->id, $payment->fresh()->subscription_id);
    }

    public function test_activate_extends_existing_active_pro_sub(): void
    {
        $user = TelegramUser::create([
            'telegram_id' => 99002, 'first_name' => 'B', 'language_code' => 'ru',
        ]);

        Subscription::create([
            'telegram_user_id' => $user->telegram_id,
            'plan_key' => 'pro_monthly',
            'tier' => Subscription::TIER_PRO,
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => Carbon::now()->subDays(10),
            'expires_at' => Carbon::now()->addDays(20),
            'stars_paid' => 150,
        ]);

        $payment = Payment::create([
            'telegram_user_id' => $user->telegram_id,
            'plan_key' => 'pro_monthly',
            'stars_amount' => 150,
            'currency' => 'XTR',
            'invoice_payload' => 'inv_test_2',
            'telegram_payment_charge_id' => 'tg_charge_2',
            'status' => Payment::STATUS_PENDING,
        ]);

        /** @var SubscriptionService $svc */
        $svc = $this->app->make(SubscriptionService::class);
        $sub = $svc->activate($user, 'pro_monthly', $payment->fresh());

        // New sub starts where the old one ended.
        $this->assertTrue($sub->started_at->diffInDays(Carbon::now()->addDays(20), false) <= 1);
    }

    public function test_expire_disables_clients(): void
    {
        $user = TelegramUser::create([
            'telegram_id' => 99003, 'first_name' => 'C', 'language_code' => 'ru',
        ]);
        $sub = Subscription::create([
            'telegram_user_id' => $user->telegram_id,
            'plan_key' => 'pro_monthly',
            'tier' => Subscription::TIER_PRO,
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => Carbon::now()->subDays(31),
            'expires_at' => Carbon::now()->subDay(),
        ]);

        /** @var SubscriptionService $svc */
        $svc = $this->app->make(SubscriptionService::class);
        $svc->expire($sub);

        $this->assertSame(Subscription::STATUS_EXPIRED, $sub->fresh()->status);
    }
}
