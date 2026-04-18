<?php

namespace App\Http\Controllers\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\TelegramUser;
use App\Services\ServerRegistryService;
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
            ],
            'servers' => $servers,
            'config' => [
                'supportUrl' => config('miniapp.support_url'),
                'deepLinkSchemes' => config('miniapp.deep_link_schemes'),
                'appStoreLinks' => config('miniapp.app_store_links'),
                'aggregatedSubscriptionUrl' => url('/sub/u/' . $user->getOrGenerateSubToken()),
                'primaryDomain' => config('vpn.primary_domain'),
            ],
        ]);
    }
}
