<?php

namespace App\Http\Controllers\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\TelegramUser;
use App\Services\ServerRegistryService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BootstrapController extends Controller
{
    public function __invoke(Request $request, ServerRegistryService $registry): JsonResponse
    {
        /** @var TelegramUser $user */
        $user = $request->attributes->get('telegramUser');

        $servers = $registry->all()->map(fn ($s) => $s->toPublicDTO()->toArray())->values();

        return response()->json([
            'user' => [
                'id' => $user->telegram_id,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'username' => $user->username,
                'photoUrl' => $user->photo_url,
                'languageCode' => $user->language_code,
                'displayName' => $user->displayName(),
                'memberSince' => $user->created_at?->toIso8601String(),
                'subToken' => $user->getOrGenerateSubToken(),
                'onboardedAt' => $user->onboarded_at?->toIso8601String(),
                'contributeSignals' => (bool) $user->ooni_contribute,
                'ooniWatchlist' => (array) ($user->ooni_watchlist ?? []),
            ],
            'servers' => $servers,
            'config' => [
                'supportUrl' => config('miniapp.support_url'),
                'deepLinkSchemes' => config('miniapp.deep_link_schemes'),
                'appStoreLinks' => config('miniapp.app_store_links'),
                'aggregatedSubscriptionUrl' => url('/sub/u/' . $user->getOrGenerateSubToken()),
                'primaryDomain' => config('vpn.primary_domain'),
            ],
            'subscription' => $this->subscriptionPayload($user),
        ]);
    }

    private function subscriptionPayload(TelegramUser $user): array
    {
        $active = $user->activeSubscription();

        return [
            'active' => $active ? [
                'id' => $active->id,
                'planKey' => $active->plan_key,
                'tier' => $active->tier,
                'status' => $active->status,
                'startedAt' => $active->started_at?->toIso8601String(),
                'expiresAt' => $active->expires_at?->toIso8601String(),
                'trafficCapBytes' => $active->trafficCapBytes(),
                'isTrial' => $active->isTrial(),
            ] : null,
            'plans' => $this->plansCatalogue(),
        ];
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
