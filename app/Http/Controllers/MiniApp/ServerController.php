<?php

namespace App\Http\Controllers\MiniApp;

use App\Exceptions\VpnPanel\ClientCreationException;
use App\Http\Controllers\Controller;
use App\Models\TelegramUser;
use App\Services\AggregatedSubscriptionService;
use App\Services\ClientProvisioningService;
use App\Services\LinkService;
use App\Services\ServerRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerController extends Controller
{
    public function index(Request $request, ServerRegistryService $registry): JsonResponse
    {
        $servers = $registry->all()->map(fn ($s) => $s->toPublicDTO()->toArray())->values();

        return response()->json(['servers' => $servers]);
    }

    public function connect(
        Request $request,
        string $slug,
        ServerRegistryService $registry,
        ClientProvisioningService $provisioner,
        LinkService $links,
        AggregatedSubscriptionService $aggregated,
    ): JsonResponse {
        /** @var TelegramUser $user */
        $user = $request->attributes->get('telegramUser');

        $server = $registry->findBySlug($slug);

        if (!$server || !$server->is_active) {
            return response()->json(['error' => 'not_found'], 404);
        }

        if ($server->is_coming_soon) {
            return response()->json([
                'error' => 'coming_soon',
                'message' => 'This server is not available yet',
            ], 422);
        }

        try {
            $client = $provisioner->provision($user, $server);
        } catch (ClientCreationException $e) {
            return response()->json([
                'error' => 'provision_failed',
                'message' => $e->getMessage(),
            ], 502);
        }

        $aggregated->invalidate($user);

        // Hand back the universal (aggregated) subscription, not the per-server
        // 3x-ui sub URL. Laravel builds the universal link from the DB + live
        // inbound so it always resolves and self-heals; the 3x-ui sub server
        // 400s whenever a client's sub_id has drifted from the panel.
        $subscriptionUrl = url('/sub/u/' . $user->getOrGenerateSubToken());
        $devices = ['ios', 'android', 'macos', 'desktop'];
        $deepLinks = [];
        foreach ($devices as $device) {
            $deepLinks[$device] = $links->importLinkForUrl($subscriptionUrl, $device);
        }

        return response()->json([
            'server' => $server->toPublicDTO()->toArray(),
            'subscriptionUrl' => $subscriptionUrl,
            'deepLinks' => $deepLinks,
            'redirectUrlBase' => 'https://' . config('vpn.primary_domain') . '/vpn-link?url=',
            'appStoreLinks' => $links->appStoreLinks(),
            'provisionedAt' => $client->created_at?->toIso8601String(),
        ]);
    }
}
