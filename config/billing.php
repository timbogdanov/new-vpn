<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Plan catalogue
    |--------------------------------------------------------------------------
    |
    | Tiny, code-coupled — kept in config rather than DB. To change pricing,
    | bump a `plan_key` (so historical Subscription/Payment rows still resolve
    | back to the plan they were sold under).
    |
    | `stars` is the XTR amount Telegram charges. `traffic_bytes = null` means
    | unlimited; numeric value enforces a cap via the `billing:enforce-quotas`
    | command. `device_limit` mirrors 3x-ui's `limitIp`.
    |
    */

    'plans' => [
        'trial_7d' => [
            'tier' => 'trial',
            'name_key' => 'billing.plans.trial_7d.name',
            'description_key' => 'billing.plans.trial_7d.description',
            'duration_days' => 7,
            'traffic_bytes' => 5 * 1024 * 1024 * 1024, // 5 GB
            'device_limit' => 1,
            'stars' => 0,
            'visible_in_paywall' => false,
        ],

        'pro_monthly' => [
            'tier' => 'pro',
            'name_key' => 'billing.plans.pro_monthly.name',
            'description_key' => 'billing.plans.pro_monthly.description',
            'duration_days' => 30,
            'traffic_bytes' => null,
            'device_limit' => 3,
            'stars' => 150,
            'visible_in_paywall' => true,
            'highlight' => false,
        ],

        'pro_annual' => [
            'tier' => 'pro_annual',
            'name_key' => 'billing.plans.pro_annual.name',
            'description_key' => 'billing.plans.pro_annual.description',
            'duration_days' => 365,
            'traffic_bytes' => null,
            'device_limit' => 5,
            'stars' => 1290, // ~28% off vs 12 × 150
            'visible_in_paywall' => true,
            'highlight' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial defaults
    |--------------------------------------------------------------------------
    */

    'trial' => [
        'plan_key' => 'trial_7d',
        'auto_grant_on_first_visit' => true,
        'expiry_warning_threshold_hours' => 48,
        'quota_warning_percent' => 80,
    ],

    /*
    |--------------------------------------------------------------------------
    | Stars → fiat estimate (display only)
    |--------------------------------------------------------------------------
    |
    | Used to render an approximate USD figure next to the "★ N" price. Stars
    | conversion floats; refresh occasionally. Display is always labelled
    | "approximate" — it is not a real exchange rate.
    |
    */

    'stars_per_usd_estimate' => 50,

    /*
    |--------------------------------------------------------------------------
    | Quota enforcement
    |--------------------------------------------------------------------------
    */

    'enforcement' => [
        // Disable client in 3x-ui when used >= quota_bytes.
        'hard_cutoff' => true,
        // Run frequency for the billing:enforce-quotas command.
        'schedule_cron' => '* * * * *',
        // Disable reason string written to vpn_clients.disabled_reason.
        'reason_quota' => 'quota_exhausted',
        'reason_expired' => 'subscription_expired',
    ],
];
