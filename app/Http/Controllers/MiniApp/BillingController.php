<?php

namespace App\Http\Controllers\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\TelegramUser;
use App\Services\TelegramBillingService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    public function plans(): JsonResponse
    {
        return response()->json(['plans' => $this->plansCatalogue()]);
    }

    public function invoice(Request $request, TelegramBillingService $billing): JsonResponse
    {
        /** @var TelegramUser $user */
        $user = $request->attributes->get('telegramUser');

        $planKey = (string) $request->input('planKey', '');
        $plan = (array) config("billing.plans.{$planKey}", []);
        if ($plan === [] || !($plan['visible_in_paywall'] ?? false)) {
            return response()->json(['error' => 'unknown_plan'], 422);
        }
        $stars = (int) ($plan['stars'] ?? 0);
        if ($stars <= 0) {
            return response()->json(['error' => 'plan_not_purchasable'], 422);
        }

        $locale = $user->language_code ?: 'ru';
        $title = Lang::get($plan['name_key'] ?? '', [], $locale) ?: ucfirst(str_replace('_', ' ', $planKey));
        $description = Lang::get($plan['description_key'] ?? '', [], $locale) ?: $title;

        $payload = 'sub_' . $user->telegram_id . '_' . $planKey . '_' . Str::random(16);

        $payment = Payment::create([
            'telegram_user_id' => $user->telegram_id,
            'plan_key' => $planKey,
            'stars_amount' => $stars,
            'currency' => (string) config('telegram.stars_currency', 'XTR'),
            'invoice_payload' => $payload,
            'status' => Payment::STATUS_PENDING,
        ]);

        try {
            $link = $billing->createInvoiceLink($title, $description, $payload, $stars);
        } catch (\Throwable $e) {
            $payment->update(['status' => Payment::STATUS_FAILED]);
            return response()->json([
                'error' => 'invoice_create_failed',
                'message' => __('billing.errors.payment_failed'),
            ], 502);
        }

        return response()->json([
            'invoiceLink' => $link,
            'invoicePayload' => $payload,
            'stars' => $stars,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        /** @var TelegramUser $user */
        $user = $request->attributes->get('telegramUser');

        $rows = Payment::forUser($user->telegram_id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $payments = $rows->map(fn (Payment $p) => [
            'id' => $p->id,
            'planKey' => $p->plan_key,
            'stars' => $p->stars_amount,
            'currency' => $p->currency,
            'status' => $p->status,
            'paidAt' => $p->paid_at?->toIso8601String(),
            'refundedAt' => $p->refunded_at?->toIso8601String(),
            'createdAt' => $p->created_at?->toIso8601String(),
        ])->values();

        return response()->json(['payments' => $payments]);
    }

    private function plansCatalogue(): array
    {
        $out = [];
        foreach ((array) config('billing.plans', []) as $key => $plan) {
            if (!($plan['visible_in_paywall'] ?? false)) {
                continue;
            }
            $stars = (int) ($plan['stars'] ?? 0);
            $out[] = [
                'key' => $key,
                'tier' => $plan['tier'] ?? null,
                'nameKey' => $plan['name_key'] ?? null,
                'descriptionKey' => $plan['description_key'] ?? null,
                'durationDays' => (int) ($plan['duration_days'] ?? 0),
                'trafficCapBytes' => $plan['traffic_bytes'] ?? null,
                'deviceLimit' => (int) ($plan['device_limit'] ?? 0),
                'stars' => $stars,
                'usdEstimate' => $stars > 0 ? Money::starsToUsdEstimate($stars) : null,
                'highlight' => (bool) ($plan['highlight'] ?? false),
            ];
        }
        return $out;
    }
}
