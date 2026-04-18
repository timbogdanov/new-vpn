<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mini App Configuration
    |--------------------------------------------------------------------------
    */

    'app_url' => env('MINIAPP_APP_URL', env('APP_URL')) . '/app',

    'support_url' => env('MINIAPP_SUPPORT_URL'),

    'init_data_ttl' => (int) env('MINIAPP_INIT_DATA_TTL', 86400),

    'last_active_throttle_seconds' => (int) env('MINIAPP_LAST_ACTIVE_THROTTLE', 60),

    'aggregated_sub_cache_seconds' => (int) env('MINIAPP_AGGREGATED_SUB_CACHE', 60),

    'provision_lock_seconds' => 10,

    'server_stats_cache_seconds' => 300,

    /*
    |--------------------------------------------------------------------------
    | Device → deep link scheme mapping
    |--------------------------------------------------------------------------
    | Mirrors what LinkService builds. The Mini App frontend also uses this
    | mapping via the /bootstrap response for client-side device detection.
    */

    'deep_link_schemes' => [
        'ios' => 'v2raytun://import/{url}',
        'macos' => 'v2raytun://import/{url}',
        'android' => 'v2raytun://import-sub?url={url}',
        'tdesktop' => 'hiddify://import/{url}',
        'web' => 'hiddify://import/{url}',
        'weba' => 'hiddify://import/{url}',
        'webk' => 'hiddify://import/{url}',
        'desktop' => 'hiddify://import/{url}',
        'unknown' => 'v2raytun://import/{url}',
    ],

    'app_store_links' => [
        'ios' => 'https://apps.apple.com/app/v2raytun/id6476628951',
        'macos' => 'https://apps.apple.com/app/v2raytun/id6476628951',
        'android' => 'https://play.google.com/store/apps/details?id=com.v2raytun.android',
        'tdesktop' => 'https://github.com/hiddify/hiddify-app/releases/latest',
        'web' => 'https://github.com/hiddify/hiddify-app/releases/latest',
        'desktop' => 'https://github.com/hiddify/hiddify-app/releases/latest',
    ],
];
