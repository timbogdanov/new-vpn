<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OONI (Open Observatory of Network Interference) configuration.
    |--------------------------------------------------------------------------
    |
    | OONI publishes a freely-queryable dataset of internet censorship
    | measurements contributed by volunteer probes worldwide. We use their
    | aggregation API to report per-service blocking status for the user's
    | detected country + ASN, then suggest the best VPN exit to unblock.
    |
    | Attribution: "Powered by OONI" per their terms of use.
    | Docs: https://api.ooni.org/
    */

    'api_url' => env('OONI_API_URL', 'https://api.ooni.org'),

    // Polite timeout; OONI recommends sub-second-level request rates.
    'timeout' => (int) env('OONI_TIMEOUT', 12),

    // Per-(country, asn) cache TTL. OONI data is minutes-to-hours granular.
    'cache_ttl' => (int) env('OONI_CACHE_TTL', 3600),

    // Lookback window for aggregation queries (days).
    'lookback_days' => (int) env('OONI_LOOKBACK_DAYS', 7),

    // Minimum number of OONI measurements in the window for a verdict to
    // be considered reliable. Below this we mark the service 'unknown'.
    'min_measurements' => (int) env('OONI_MIN_MEASUREMENTS', 3),

    /*
    |--------------------------------------------------------------------------
    | Curated service catalog.
    |--------------------------------------------------------------------------
    |
    | Each service maps to its canonical OONI test URL(s). The first URL is
    | the primary probe; any additional entries are merged into the same
    | verdict (worst-of). Ordering here defines the default display order.
    */

    'services' => [
        ['key' => 'instagram',  'label' => 'Instagram', 'urls' => ['https://www.instagram.com/']],
        ['key' => 'youtube',    'label' => 'YouTube',   'urls' => ['https://www.youtube.com/']],
        ['key' => 'telegram',   'label' => 'Telegram',  'urls' => ['https://telegram.org/', 'https://web.telegram.org/']],
        ['key' => 'whatsapp',   'label' => 'WhatsApp',  'urls' => ['https://www.whatsapp.com/']],
        ['key' => 'signal',     'label' => 'Signal',    'urls' => ['https://signal.org/']],
        ['key' => 'x',          'label' => 'X (Twitter)', 'urls' => ['https://x.com/', 'https://twitter.com/']],
        ['key' => 'facebook',   'label' => 'Facebook',  'urls' => ['https://www.facebook.com/']],
        ['key' => 'wikipedia',  'label' => 'Wikipedia', 'urls' => ['https://www.wikipedia.org/', 'https://ru.wikipedia.org/']],
        ['key' => 'bbc',        'label' => 'BBC',       'urls' => ['https://www.bbc.com/', 'https://www.bbc.co.uk/']],
        ['key' => 'netflix',    'label' => 'Netflix',   'urls' => ['https://www.netflix.com/']],
        ['key' => 'discord',    'label' => 'Discord',   'urls' => ['https://discord.com/']],
        ['key' => 'linkedin',   'label' => 'LinkedIn',  'urls' => ['https://www.linkedin.com/']],
        ['key' => 'meduza',     'label' => 'Meduza',    'urls' => ['https://meduza.io/']],
        ['key' => 'dozhd',      'label' => 'Dozhd TV',  'urls' => ['https://tvrain.tv/']],
    ],

    /*
    |--------------------------------------------------------------------------
    | Warm-cache seed list.
    |--------------------------------------------------------------------------
    |
    | (country, asn) pairs the scheduled warmer pre-fetches so first-user
    | latency is avoided. Top RU carriers + a few CIS peers. ASN=null runs
    | the country-only query (wider, less precise). Trim/extend freely.
    */

    'seed_warm' => [
        ['country' => 'RU', 'asn' => 'AS8402'],   // Corbina / VimpelCom (Beeline)
        ['country' => 'RU', 'asn' => 'AS12389'],  // Rostelecom
        ['country' => 'RU', 'asn' => 'AS25513'],  // MTS
        ['country' => 'RU', 'asn' => 'AS31133'],  // MegaFon
        ['country' => 'RU', 'asn' => 'AS13335'],  // Cloudflare (mobile HTTP via Cloudflare)
        ['country' => 'RU', 'asn' => null],
        ['country' => 'BY', 'asn' => null],
        ['country' => 'KZ', 'asn' => null],
        ['country' => 'UA', 'asn' => null],
    ],
];
