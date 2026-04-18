<?php

return [
    'plans' => [
        'trial_7d' => [
            'name' => '7-day trial',
            'description' => 'Test every server, free for a week. 5 GB included.',
        ],
        'pro_monthly' => [
            'name' => 'Pro · Monthly',
            'description' => 'Unlimited traffic across every server. Three devices.',
        ],
        'pro_annual' => [
            'name' => 'Pro · Annual',
            'description' => 'Twelve months, five devices, lowest per-month price.',
        ],
    ],

    'errors' => [
        'unknown_invoice' => 'This invoice is no longer valid. Open the app and try again.',
        'payment_failed' => 'Payment did not go through. Nothing was charged.',
    ],

    'success' => [
        'activated' => 'You are on :plan. Enjoy.',
        'trial_granted' => 'Trial active for :days days. Welcome aboard.',
    ],

    'banners' => [
        'trial_ending' => 'Trial ends in :hours hours.',
        'expired' => 'Subscription expired. Renew to keep your servers.',
        'quota_warning' => ':percent% of trial traffic used.',
    ],

    'cta' => [
        'upgrade' => 'Upgrade',
        'renew' => 'Renew',
        'manage' => 'Manage',
    ],
];
