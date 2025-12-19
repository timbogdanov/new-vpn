<?php

namespace App\Services;

use App\DTO\VpnClientDTO;

class LinkService
{
    private string $redirectUrl;
    private string $subscriptionBaseUrl;

    public function __construct()
    {
        $primaryDomain = config('vpn.primary_domain');
        $panelDomain = config('vpn.panel_domain');
        $subscriptionPort = config('vpn.subscription_port');

        $this->redirectUrl = "https://{$primaryDomain}/vpn-link";
        $this->subscriptionBaseUrl = "https://{$panelDomain}:{$subscriptionPort}/sub";
    }

    /**
     * Create device-specific connection links
     */
    public function createLinks(VpnClientDTO $client, string $device = 'apple'): array
    {
        $subscriptionUrl = $this->subscriptionBaseUrl . '/' . $client->subId;
        $encodedUrl = rawurlencode($subscriptionUrl);

        $importLink = match (strtolower($device)) {
            'android' => "v2raytun://import-sub?url={$encodedUrl}",
            'windows' => "hiddify://import/{$encodedUrl}",
            default => "v2raytun://import/{$encodedUrl}", // Apple
        };

        return [
            'subscriptionUrl' => $subscriptionUrl,
            'importLink' => $importLink,
            'redirectUrl' => $this->redirectUrl . '?url=' . rawurlencode($importLink),
        ];
    }

    /**
     * Get QR code data for subscription URL
     */
    public function getQrCodeData(VpnClientDTO $client): string
    {
        return $this->subscriptionBaseUrl . '/' . $client->subId;
    }

    /**
     * Get app download links for each platform
     */
    public function getAppDownloadLinks(): array
    {
        return [
            'apple' => 'https://apps.apple.com/app/v2raytun/id6476628951',
            'android' => 'https://play.google.com/store/apps/details?id=com.v2raytun.android',
            'windows' => 'https://github.com/hiddify/hiddify-app/releases/latest',
        ];
    }

    /**
     * Get the current configuration
     */
    public function getConfig(): array
    {
        return [
            'redirectUrl' => $this->redirectUrl,
            'subscriptionBaseUrl' => $this->subscriptionBaseUrl,
        ];
    }
}
