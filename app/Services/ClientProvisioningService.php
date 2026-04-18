<?php

namespace App\Services;

use App\Exceptions\VpnPanel\ClientCreationException;
use App\Models\Server;
use App\Models\TelegramUser;
use App\Models\VpnClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ClientProvisioningService
{
    public function __construct(
        private readonly XuiClientFactory $xui,
    ) {}

    public function provision(TelegramUser $user, Server $server): VpnClient
    {
        if ($server->is_coming_soon) {
            throw new ClientCreationException('Server is not available yet');
        }

        $existing = VpnClient::query()
            ->where('telegram_user_id', $user->telegram_id)
            ->where('server_id', $server->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $lockSeconds = (int) config('miniapp.provision_lock_seconds', 10);
        $lock = Cache::lock(
            "provision:{$user->telegram_id}:{$server->id}",
            $lockSeconds
        );

        return $lock->block(5, function () use ($user, $server) {
            $existing = VpnClient::query()
                ->where('telegram_user_id', $user->telegram_id)
                ->where('server_id', $server->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            return $this->createOrAdopt($user, $server);
        });
    }

    private function createOrAdopt(TelegramUser $user, Server $server): VpnClient
    {
        $xui = $this->xui->forServer($server);

        // Adopt branch: a pre-existing 3x-ui client already has this tgId
        // from the bot-only era. Adopt it into our DB rather than duplicating.
        $adopted = $xui->getClientByTelegramId((int) $user->telegram_id);

        if ($adopted) {
            Log::info('Adopting existing 3x-ui client', [
                'telegram_id' => $user->telegram_id,
                'server' => $server->slug,
                'uuid' => $adopted->uuid,
            ]);

            return VpnClient::create([
                'telegram_user_id' => $user->telegram_id,
                'server_id' => $server->id,
                'uuid' => $adopted->uuid,
                'sub_id' => $adopted->subId,
                'email' => $adopted->email,
                'enabled' => $adopted->enabled,
            ]);
        }

        $created = $xui->createClient(
            (int) $user->telegram_id,
            $user->first_name,
            $user->last_name,
        );

        return VpnClient::create([
            'telegram_user_id' => $user->telegram_id,
            'server_id' => $server->id,
            'uuid' => $created->uuid,
            'sub_id' => $created->subId,
            'email' => $created->email,
            'enabled' => $created->enabled,
        ]);
    }
}
