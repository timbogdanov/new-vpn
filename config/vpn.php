<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Domain Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the domains used for the VPN service.
    | Change these values when you need to switch domains quickly
    | (e.g., when ISPs block your current domain).
    |
    */

    'primary_domain' => env('VPN_PRIMARY_DOMAIN', 'larastory.com'),
    'panel_domain' => env('VPN_PANEL_DOMAIN', 'dashboard.larastory.com'),
    'subscription_port' => env('VPN_SUBSCRIPTION_PORT', '2096'),

    /*
    |--------------------------------------------------------------------------
    | Default Client Settings
    |--------------------------------------------------------------------------
    |
    | Default settings applied when creating new VPN clients.
    |
    */

    'default_device_limit' => env('VPN_DEFAULT_DEVICE_LIMIT', 2),
    'default_traffic_limit' => env('VPN_DEFAULT_TRAFFIC_LIMIT', 0), // 0 = unlimited
];
