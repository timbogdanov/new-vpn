<?php

namespace App\Http\Controllers\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\TelegramUser;
use App\Services\AggregatedSubscriptionService;
use App\Services\XuiClientFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProfileController extends Controller
{
    public function show(Request $request, XuiClientFactory $xui): JsonResponse
    {
        /** @var TelegramUser $user */
        $user = $request->attributes->get('telegramUser');

        $clients = $user->vpnClients()->with('server')->get();

        $items = [];
        $totalUp = 0;
        $totalDown = 0;

        foreach ($clients as $client) {
            $server = $client->server;
            if (!$server) {
                continue;
            }

            $up = (int) $client->last_traffic_up;
            $down = (int) $client->last_traffic_down;

            // Refresh stale traffic (>5 min old) opportunistically, skipping on failure.
            if (!$client->last_traffic_synced_at || $client->last_traffic_synced_at->lt(Carbon::now()->subMinutes(5))) {
                try {
                    $traffic = $xui->forServer($server)->getClientTraffic($client->email);
                    $up = $traffic->upload;
                    $down = $traffic->download;
                    $client->forceFill([
                        'last_traffic_up' => $up,
                        'last_traffic_down' => $down,
                        'last_traffic_synced_at' => Carbon::now(),
                    ])->saveQuietly();
                } catch (\Throwable $e) {
                    // Silent — use cached values.
                }
            }

            $totalUp += $up;
            $totalDown += $down;

            $items[] = [
                'server' => $server->toPublicDTO()->toArray(),
                'uuid' => $client->uuid,
                'subId' => $client->sub_id,
                'since' => $client->created_at?->toIso8601String(),
                'traffic' => [
                    'up' => $up,
                    'down' => $down,
                    'total' => $up + $down,
                ],
            ];
        }

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
            ],
            'clients' => $items,
            'totalTraffic' => [
                'up' => $totalUp,
                'down' => $totalDown,
                'total' => $totalUp + $totalDown,
            ],
            'aggregatedSubscriptionUrl' => url('/sub/u/' . $user->getOrGenerateSubToken()),
        ]);
    }

    public function update(Request $request, AggregatedSubscriptionService $aggregated): JsonResponse
    {
        /** @var TelegramUser $user */
        $user = $request->attributes->get('telegramUser');

        $data = $request->validate([
            'languageCode' => ['nullable', 'string', 'in:ru,en'],
            'rotateSubToken' => ['nullable', 'boolean'],
            'onboarded' => ['nullable', 'boolean'],
            'contributeSignals' => ['nullable', 'boolean'],
        ]);

        if (!empty($data['languageCode'])) {
            $user->language_code = $data['languageCode'];
            $user->save();
        }

        if (!empty($data['rotateSubToken'])) {
            $user->rotateSubToken();
            $aggregated->invalidate($user);
        }

        if (!empty($data['onboarded']) && $user->onboarded_at === null) {
            $user->forceFill(['onboarded_at' => Carbon::now()])->save();
        }

        if (array_key_exists('contributeSignals', $data) && $data['contributeSignals'] !== null) {
            $user->ooni_contribute = (bool) $data['contributeSignals'];
            $user->save();
        }

        return response()->json([
            'user' => [
                'id' => $user->telegram_id,
                'languageCode' => $user->language_code,
                'subToken' => $user->getOrGenerateSubToken(),
                'onboardedAt' => $user->onboarded_at?->toIso8601String(),
                'contributeSignals' => (bool) $user->ooni_contribute,
            ],
            'aggregatedSubscriptionUrl' => url('/sub/u/' . $user->getOrGenerateSubToken()),
        ]);
    }
}
