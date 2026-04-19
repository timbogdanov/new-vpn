<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OONI (Open Observatory of Network Interference) configuration.
    |--------------------------------------------------------------------------
    |
    | OONI publishes a freely-queryable dataset of internet censorship
    | measurements contributed by volunteer probes worldwide. We use their
    | aggregation API to report per-URL blocking status for the user's
    | detected country + ASN, then suggest the best VPN exit to unblock.
    |
    | Attribution: "Powered by OONI" per their terms of use.
    | Docs: https://api.ooni.org/
    */

    'api_url' => env('OONI_API_URL', 'https://api.ooni.org'),

    // Polite timeout; OONI recommends sub-second-level request rates.
    'timeout' => (int) env('OONI_TIMEOUT', 12),

    // Per-(country, asn) summary cache TTL. OONI data is minutes-to-hours granular.
    'cache_ttl' => (int) env('OONI_CACHE_TTL', 3600),

    // Per-URL details cache TTL (shorter — user-driven queries, may move faster).
    'details_cache_ttl' => (int) env('OONI_DETAILS_CACHE_TTL', 900),

    // Search typeahead cache TTL.
    'search_cache_ttl' => (int) env('OONI_SEARCH_CACHE_TTL', 3600),

    // Lookback window for the top-blocked grid (days).
    'lookback_days' => (int) env('OONI_LOOKBACK_DAYS', 7),

    // Lookback window for per-URL details (day-by-day timeseries).
    'details_lookback_days' => (int) env('OONI_DETAILS_LOOKBACK_DAYS', 30),

    // How many recent measurement records to show on the details page.
    'details_measurements_limit' => (int) env('OONI_DETAILS_MEASUREMENTS', 20),

    // How many ASN rows to show in the "other networks" breakdown.
    'asn_breakdown_limit' => (int) env('OONI_ASN_BREAKDOWN_LIMIT', 8),

    // How many rows to render in the Freedom Map dynamic grid.
    'top_blocked_display_limit' => (int) env('OONI_TOP_BLOCKED_DISPLAY', 15),

    // Soft-delete retention for community_probe_signals before hard purge.
    'signal_tombstone_days' => (int) env('OONI_TOMBSTONE_DAYS', 30),

    // Minimum number of OONI measurements in the window for a verdict to
    // be considered reliable. Below this we mark the service 'unknown'.
    'min_measurements' => (int) env('OONI_MIN_MEASUREMENTS', 3),

    /*
    |--------------------------------------------------------------------------
    | URL normalization rules.
    |--------------------------------------------------------------------------
    |
    | A single canonical form is used for cache keys, watchlist storage, and
    | matching against OONI's `input` field. OONI is sensitive to exact URLs
    | — `https://x.com` and `https://x.com/` are different rows — so we always
    | trailing-slash roots and preserve www subdomain distinctions.
    */
    'normalization' => [
        'force_https'          => true,
        'strip_query'          => true,
        'strip_fragment'       => true,
        'lowercase_host'       => true,
        'strip_trailing_slash' => false,
        'strip_www'            => false,
        'allow_path'           => true,
        'max_url_length'       => 512,
    ],

    /*
    |--------------------------------------------------------------------------
    | Catalog of URLs aggregated on the Freedom Map grid.
    |--------------------------------------------------------------------------
    |
    | Each service maps to its canonical OONI test URL(s). The first URL is
    | the primary probe; any additional entries are merged into the same
    | verdict (worst-of). The grid is sorted dynamically by verdict severity
    | at render time, so ordering here is irrelevant — only membership is.
    |
    | Broad catalog (~40 items) so per-country ranking has enough signal.
    */

    'services' => [
        // Mainstream platforms
        ['key' => 'instagram',   'label' => 'Instagram',    'urls' => ['https://www.instagram.com/']],
        ['key' => 'youtube',     'label' => 'YouTube',      'urls' => ['https://www.youtube.com/']],
        ['key' => 'telegram',    'label' => 'Telegram',     'urls' => ['https://telegram.org/', 'https://web.telegram.org/']],
        ['key' => 'whatsapp',    'label' => 'WhatsApp',     'urls' => ['https://www.whatsapp.com/']],
        ['key' => 'signal',      'label' => 'Signal',       'urls' => ['https://signal.org/']],
        ['key' => 'x',           'label' => 'X (Twitter)',  'urls' => ['https://x.com/', 'https://twitter.com/']],
        ['key' => 'facebook',    'label' => 'Facebook',     'urls' => ['https://www.facebook.com/']],
        ['key' => 'tiktok',      'label' => 'TikTok',       'urls' => ['https://www.tiktok.com/']],
        ['key' => 'reddit',      'label' => 'Reddit',       'urls' => ['https://www.reddit.com/']],
        ['key' => 'discord',     'label' => 'Discord',      'urls' => ['https://discord.com/']],
        ['key' => 'linkedin',    'label' => 'LinkedIn',     'urls' => ['https://www.linkedin.com/']],

        // Knowledge + search
        ['key' => 'wikipedia',   'label' => 'Wikipedia',    'urls' => ['https://www.wikipedia.org/', 'https://ru.wikipedia.org/']],
        ['key' => 'google',      'label' => 'Google',       'urls' => ['https://www.google.com/']],
        ['key' => 'duckduckgo',  'label' => 'DuckDuckGo',   'urls' => ['https://duckduckgo.com/']],

        // AI
        ['key' => 'chatgpt',     'label' => 'ChatGPT',      'urls' => ['https://chatgpt.com/', 'https://chat.openai.com/']],
        ['key' => 'claude',      'label' => 'Claude',       'urls' => ['https://claude.ai/']],
        ['key' => 'perplexity',  'label' => 'Perplexity',   'urls' => ['https://www.perplexity.ai/']],

        // Dev / infra
        ['key' => 'github',      'label' => 'GitHub',       'urls' => ['https://github.com/']],
        ['key' => 'medium',      'label' => 'Medium',       'urls' => ['https://medium.com/']],
        ['key' => 'proton',      'label' => 'Proton',       'urls' => ['https://proton.me/', 'https://protonmail.com/']],
        ['key' => 'tor',         'label' => 'Tor Project',  'urls' => ['https://www.torproject.org/']],
        ['key' => 'tutanota',    'label' => 'Tutanota',     'urls' => ['https://tuta.com/', 'https://tutanota.com/']],

        // Streaming
        ['key' => 'netflix',     'label' => 'Netflix',      'urls' => ['https://www.netflix.com/']],
        ['key' => 'spotify',     'label' => 'Spotify',      'urls' => ['https://www.spotify.com/']],
        ['key' => 'twitch',      'label' => 'Twitch',       'urls' => ['https://www.twitch.tv/']],

        // International press
        ['key' => 'bbc',         'label' => 'BBC',          'urls' => ['https://www.bbc.com/', 'https://www.bbc.co.uk/']],
        ['key' => 'bbc_russian', 'label' => 'BBC Russian',  'urls' => ['https://www.bbc.com/russian']],
        ['key' => 'dw',          'label' => 'Deutsche Welle', 'urls' => ['https://www.dw.com/', 'https://www.dw.com/ru/']],
        ['key' => 'reuters',     'label' => 'Reuters',      'urls' => ['https://www.reuters.com/']],
        ['key' => 'nytimes',     'label' => 'NY Times',     'urls' => ['https://www.nytimes.com/']],
        ['key' => 'guardian',    'label' => 'The Guardian', 'urls' => ['https://www.theguardian.com/']],

        // Regional independent press
        ['key' => 'meduza',      'label' => 'Meduza',       'urls' => ['https://meduza.io/']],
        ['key' => 'dozhd',       'label' => 'Dozhd TV',     'urls' => ['https://tvrain.tv/']],
        ['key' => 'svoboda',     'label' => 'Radio Svoboda', 'urls' => ['https://www.svoboda.org/', 'https://www.radiosvoboda.org/']],
        ['key' => 'novaya',      'label' => 'Novaya Gazeta Europe', 'urls' => ['https://novayagazeta.eu/']],
        ['key' => 'currenttime', 'label' => 'Current Time', 'urls' => ['https://www.currenttime.tv/']],

        // Circumvention-adjacent
        ['key' => 'nitter',      'label' => 'Nitter',       'urls' => ['https://nitter.net/']],
        ['key' => 'element',     'label' => 'Element',      'urls' => ['https://element.io/']],
    ],

    /*
    |--------------------------------------------------------------------------
    | Warm-cache seed list.
    |--------------------------------------------------------------------------
    |
    | (country, asn) pairs the scheduled warmer pre-fetches so first-user
    | latency is avoided. Top RU carriers + a few CIS peers. ASN=null runs
    | the country-only query (wider, less precise).
    */

    'seed_warm' => [
        ['country' => 'RU', 'asn' => 'AS8402'],
        ['country' => 'RU', 'asn' => 'AS12389'],
        ['country' => 'RU', 'asn' => 'AS25513'],
        ['country' => 'RU', 'asn' => 'AS31133'],
        ['country' => 'RU', 'asn' => 'AS13335'],
        ['country' => 'RU', 'asn' => null],
        ['country' => 'BY', 'asn' => null],
        ['country' => 'KZ', 'asn' => null],
        ['country' => 'UA', 'asn' => null],
    ],
];
