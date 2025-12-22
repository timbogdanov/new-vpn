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
    | Async Request (Optional)
    |--------------------------------------------------------------------------
    |
    | Set to true to use async requests for better performance.
    | Requires a properly configured queue system.
    |
    */

    'async_requests' => env('TELEGRAM_ASYNC_REQUESTS', false),
];
