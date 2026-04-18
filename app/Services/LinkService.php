<?php

namespace App\Services;

use App\Models\Server;
use App\Models\VpnClient;

class LinkService
{
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

    public function appStoreLinks(): array
    {
        return (array) config('miniapp.app_store_links', []);
    }
}
