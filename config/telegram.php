<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Configuration
    |--------------------------------------------------------------------------
    */

    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    'bot_username' => env('TELEGRAM_BOT_USERNAME'),

    'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Webhook secret token
    |--------------------------------------------------------------------------
    |
    | Set this to a random 1-256 char string and pass it to setWebhook as the
    | `secret_token` parameter. Telegram echoes it back in the
    | `X-Telegram-Bot-Api-Secret-Token` header on every update; the controller
    | rejects (with 200 OK to suppress retry storms) anything that doesn't
    | match.
    |
    */

    'webhook_secret_token' => env('TELEGRAM_WEBHOOK_SECRET_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Telegram Stars (XTR)
    |--------------------------------------------------------------------------
    */

    'stars_currency' => 'XTR',

    /*
    |--------------------------------------------------------------------------
    | Async Request (Optional)
    |--------------------------------------------------------------------------
    |
    | Set to true to use async requests for better performance.
    | Requires a properly configured queue system.
    |
    */

    'async_requests' => env('TELEGRAM_ASYNC_REQUESTS', false),
];
