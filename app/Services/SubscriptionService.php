<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\TelegramUser;
use App\Models\VpnClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SubscriptionService
{
    public function grantTrial(TelegramUser $user): ?Subscription
    {
        if (!config('billing.trial.auto_grant_on_first_visit', true)) {
            return null;
        }
        if ($user->trial_used_at !== null) {
            return null;
        }
        if ($user->activeSubscription() !== null) {
            return null;
        }

        $planKey = (string) config('billing.trial.plan_key', 'trial_7d');
        $plan = (array) config("billing.plans.{$planKey}", []);
        if ($plan === []) {
            Log::warning('SubscriptionService: trial plan not configured', ['planKey' => $planKey]);
            return null;
        }

        return DB::transaction(function () use ($user, $planKey, $plan) {
            $sub = Subscription::create([
                'telegram_user_id' => $user->telegram_id,
                'plan_key' => $planKey,
                'tier' => $plan['tier'] ?? Subscription::TIER_TRIAL,
                'status' => Subscription::STATUS_TRIALING,
                'started_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addDays((int) ($plan['duration_days'] ?? 7)),
            ]);

            $user->forceFill(['trial_used_at' => Carbon::now()])->saveQuietly();

            $this->propagateLimitsToClients($user, $sub);

            Log::info('Trial granted', [
                'telegram_id' => $user->telegram_id,
                'expires_at' => $sub->expires_at?->toIso8601String(),
            ]);

            return $sub;
        });
    }

    public function activate(TelegramUser $user, string $planKey, Payment $payment): Subscription
    {
        $plan = (array) config("billing.plans.{$planKey}", []);
        if ($plan === []) {
            throw new RuntimeException("Unknown plan: {$planKey}");
        }

        return DB::transaction(function () use ($user, $planKey, $plan, $payment) {
            $existing = Subscription::forUser($user->telegram_id)->active()->first();

            $startsAt = Carbon::now();
            $extendFromExisting = $existing
                && in_array($existing->tier, [Subscription::TIER_PRO, Subscription::TIER_PRO_ANNUAL], true)
                && $existing->expires_at?->isFuture();

            if ($extendFromExisting) {
                $startsAt = $existing->expires_at;
                $existing->update(['status' => Subscription::STATUS_CANCELED, 'canceled_at' => Carbon::now()]);
            } elseif ($existing) {
                // Trial → paid: kill the trial.
                $existing->update(['status' => Subscription::STATUS_CANCELED, 'canceled_at' => Carbon::now()]);
            }

            $sub = Subscription::create([
                'telegram_user_id' => $user->telegram_id,
                'plan_key' => $planKey,
                'tier' => $plan['tier'] ?? Subscription::TIER_PRO,
                'status' => Subscription::STATUS_ACTIVE,
                'started_at' => $startsAt,
                'expires_at' => $startsAt->copy()->addDays((int) ($plan['duration_days'] ?? 30)),
                'stars_paid' => $payment->stars_amount,
                'telegram_payment_charge_id' => $payment->telegram_payment_charge_id,
                'telegram_provider_payment_charge_id' => $payment->provider_payment_charge_id,
                'auto_renew' => false,
            ]);

            $payment->update([
                'subscription_id' => $sub->id,
                'status' => Payment::STATUS_PAID,
                'paid_at' => Carbon::now(),
            ]);

            $this->propagateLimitsToClients($user, $sub);

            Log::info('Subscription activated', [
                'telegram_id' => $user->telegram_id,
                'plan_key' => $planKey,
                'expires_at' => $sub->expires_at?->toIso8601String(),
            ]);

            return $sub;
        });
    }

    public function extend(Subscription $sub, int $days): Subscription
    {
        $sub->forceFill([
            'expires_at' => ($sub->expires_at ?: Carbon::now())->copy()->addDays($days),
        ])->save();

        $this->propagateLimitsToClients($sub->telegramUser, $sub);

        return $sub;
    }

    public function expire(Subscription $sub): Subscription
    {
        $sub->update(['status' => Subscription::STATUS_EXPIRED]);

        $reason = (string) config('billing.enforcement.reason_expired', 'subscription_expired');
        VpnClient::query()
            ->where('telegram_user_id', $sub->telegram_user_id)
            ->where('enabled', true)
            ->update(['enabled' => false, 'disabled_reason' => $reason]);

        return $sub;
    }

    public function refund(Subscription $sub, Payment $payment): Subscription
    {
        return DB::transaction(function () use ($sub, $payment) {
            $sub->update([
                'status' => Subscription::STATUS_REFUNDED,
                'refunded_at' => Carbon::now(),
            ]);
            $payment->update([
                'status' => Payment::STATUS_REFUNDED,
                'refunded_at' => Carbon::now(),
            ]);

            VpnClient::query()
                ->where('telegram_user_id', $sub->telegram_user_id)
                ->where('enabled', true)
                ->update(['enabled' => false, 'disabled_reason' => 'refunded']);

            return $sub;
        });
    }

    /**
     * Mirror the subscription's quota / expiry onto every VpnClient row for
     * this user. The actual 3x-ui-side enforcement happens in
     * billing:enforce-quotas (Phase 2) — here we just stage the desired state
     * in our DB so other code paths (UI banners, /connect blocking) can read
     * it without having to re-derive from the subscription each time.
     */
    private function propagateLimitsToClients(TelegramUser $user, Subscription $sub): void
    {
        $cap = $sub->trafficCapBytes();
        $expiresAt = $sub->expires_at;

        $update = [
            'quota_bytes' => $cap,
            'expires_at' => $expiresAt,
        ];

        // An active/future subscription must clear a prior billing-disable so a
        // renewal after a lapse actually restores access. Panel-side enable is
        // then reconciled by billing:enforce-quotas' re-enable pass.
        if ($expiresAt === null || $expiresAt->isFuture()) {
            $update['enabled'] = true;
            $update['disabled_reason'] = null;
        }

        VpnClient::query()
            ->where('telegram_user_id', $user->telegram_id)
            ->update($update);
    }
}
