<?php

/*
|--------------------------------------------------------------------------
| Server Registry
|--------------------------------------------------------------------------
|
| The source of truth for the list of VPN servers the Mini App exposes.
| Populated at deploy time by the ServerSeeder — each entry is an
| idempotent upsert keyed by `slug`.
|
| You can configure the registry in two ways:
|
|  1. SERVERS_JSON env — a JSON array of objects. Preferred for Dokploy
|     so you can add/edit servers without a code change. Example:
|
|     SERVERS_JSON=[{"slug":"us-hetzner-1","name":"Helsinki Fast",...},...]
|
|  2. The fallback below — used when SERVERS_JSON is empty or invalid.
|     It reconstructs the legacy single-server setup from the XUI_*
|     env vars, so the Mini App works on day one without touching env.
|
| Each entry supports the following keys (see the Server migration):
|  slug*, name*, country*, country_code*, city, flag_emoji, host*, port,
|  protocol, inbound_id, xui_host, xui_port, xui_path, xui_username,
|  xui_password, reality_public_key, reality_short_id, reality_sni,
|  reality_fingerprint, reality_spider_x, subscription_host,
|  subscription_port, is_active, is_coming_soon, capacity_clients,
|  tags (array), description, sort_order
|
*/

$fromEnv = env('SERVERS_JSON');
$parsed = null;

if (is_string($fromEnv) && $fromEnv !== '') {
    $decoded = json_decode($fromEnv, true);
    if (is_array($decoded)) {
        $parsed = $decoded;
    }
}

if ($parsed === null) {
    // Fallback: reconstruct one server from legacy XUI_* env.
    $parsed = [
        [
            'slug' => 'primary',
            'name' => 'Primary',
            'country' => 'United States',
            'country_code' => 'US',
            'city' => 'Hillsboro',
            'flag_emoji' => '🇺🇸',
            'host' => env('VPN_PANEL_DOMAIN', 'dashboard.larastory.com'),
            'port' => 8443,
            'protocol' => 'vless',
            'inbound_id' => (int) env('XUI_INBOUND_ID', 1),
            'xui_host' => env('XUI_HOST', '3x-ui'),
            'xui_port' => (int) env('XUI_PORT', 2053),
            'xui_path' => env('XUI_PATH', ''),
            'xui_username' => env('XUI_USERNAME'),
            'xui_password' => env('XUI_PASSWORD'),
            // Subscription endpoint must be a real TLS origin with a valid cert
            // (VPN apps use iOS URLSession / equivalent that refuses self-signed
            // or plaintext hosts). We front the 3x-ui subscription listener
            // behind `larastory.com`'s Let's Encrypt TLS via the nginx `/sub/`
            // proxy in `docker/nginx/default.conf`. Override with
            // `VPN_SUBSCRIPTION_HOST` if a dedicated subdomain is needed.
            'subscription_host' => env('VPN_SUBSCRIPTION_HOST', env('VPN_PRIMARY_DOMAIN', 'larastory.com')),
            'subscription_port' => (int) env('VPN_SUBSCRIPTION_PORT', 443),
            'is_active' => true,
            'is_coming_soon' => false,
            'capacity_clients' => 500,
            'tags' => ['recommended'],
            'description' => 'Fast, reliable gateway. Built to unblock.',
            'sort_order' => 0,
        ],
        // Placeholder slots: delightful "coming soon" cards that tell the
        // story of the product expanding — flip `is_coming_soon` to false
        // and fill in credentials once real servers are provisioned.
        [
            'slug' => 'de-frankfurt',
            'name' => 'Frankfurt',
            'country' => 'Germany',
            'country_code' => 'DE',
            'city' => 'Frankfurt',
            'flag_emoji' => '🇩🇪',
            'host' => 'de.example.com',
            'port' => 443,
            'protocol' => 'vless',
            'inbound_id' => 1,
            'is_active' => true,
            'is_coming_soon' => true,
            'capacity_clients' => 500,
            'tags' => ['coming-soon'],
            'description' => 'Low-latency EU gateway. Arriving next.',
            'sort_order' => 10,
        ],
        [
            'slug' => 'nl-amsterdam',
            'name' => 'Amsterdam',
            'country' => 'Netherlands',
            'country_code' => 'NL',
            'city' => 'Amsterdam',
            'flag_emoji' => '🇳🇱',
            'host' => 'nl.example.com',
            'port' => 443,
            'protocol' => 'vless',
            'inbound_id' => 1,
            'is_active' => true,
            'is_coming_soon' => true,
            'capacity_clients' => 500,
            'tags' => ['coming-soon'],
            'description' => 'High-bandwidth Europe hub.',
            'sort_order' => 20,
        ],
        [
            'slug' => 'sg-singapore',
            'name' => 'Singapore',
            'country' => 'Singapore',
            'country_code' => 'SG',
            'city' => 'Singapore',
            'flag_emoji' => '🇸🇬',
            'host' => 'sg.example.com',
            'port' => 443,
            'protocol' => 'vless',
            'inbound_id' => 1,
            'is_active' => true,
            'is_coming_soon' => true,
            'capacity_clients' => 500,
            'tags' => ['coming-soon'],
            'description' => 'Asia-Pacific gateway for fast APAC routing.',
            'sort_order' => 30,
        ],
    ];
}

return [
    'registry' => $parsed,
];
