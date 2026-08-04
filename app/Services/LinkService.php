<?php

namespace App\Services;

use App\DTO\VpnClientDTO;
use App\Models\Server;
use App\Models\VpnClient;

class LinkService
{
    public function __construct(
        private readonly XuiClientFactory $xui,
    ) {}

    /**
     * Raw, self-contained vless:// config for a client on a server — host,
     * uuid and live Reality params all embedded, so it connects with no
     * subscription fetch (works even if the sub domain is blocked). Null if
     * the panel inbound can't be read.
     */
    public function configTextFor(VpnClient $client, Server $server): ?string
    {
        return $this->xui->forServer($server)->getVlessLink(
            new VpnClientDTO(
                uuid: $client->uuid,
                email: $client->email,
                telegramId: (int) $client->telegram_user_id,
                subId: $client->sub_id,
                enabled: (bool) $client->enabled,
                expiryTime: 0,
                totalGB: 0,
            ),
            serverAddress: $server->host,
            remark: $server->flag_emoji . ' ' . $server->name,
        );
    }

    public function createLinks(VpnClient $client, Server $server, string $device = 'ios'): array
    {
        $subscriptionUrl = $this->subscriptionUrlFor($client, $server);
        $encodedUrl = rawurlencode($subscriptionUrl);
        $device = strtolower($device);

        $schemes = (array) config('miniapp.deep_link_schemes', []);
        $template = $schemes[$device] ?? $schemes['unknown'] ?? 'v2raytun://import/{url}';
        $importLink = str_replace('{url}', $encodedUrl, $template);

        $primaryDomain = (string) config('vpn.primary_domain');
        $redirectUrl = "https://{$primaryDomain}/vpn-link?url=" . rawurlencode($importLink);

        return [
            'subscriptionUrl' => $subscriptionUrl,
            'importLink' => $importLink,
            'redirectUrl' => $redirectUrl,
            'device' => $device,
        ];
    }

    public function subscriptionUrlFor(VpnClient $client, Server $server): string
    {
        return rtrim($server->subscriptionBaseUrl(), '/') . '/' . $client->sub_id;
    }

    /**
     * Build a device-specific import deep link (v2raytun:// / hiddify://)
     * wrapping an arbitrary subscription URL — e.g. the universal
     * /sub/u/{token} endpoint the connect flow now hands out.
     */
    public function importLinkForUrl(string $subscriptionUrl, string $device = 'ios'): string
    {
        $device = strtolower($device);
        $schemes = (array) config('miniapp.deep_link_schemes', []);
        $template = $schemes[$device] ?? $schemes['unknown'] ?? 'v2raytun://import/{url}';

        return str_replace('{url}', rawurlencode($subscriptionUrl), $template);
    }

    public function appStoreLinks(): array
    {
        return (array) config('miniapp.app_store_links', []);
    }
}
