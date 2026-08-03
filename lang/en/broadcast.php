<?php

return [
    // Server-migration reconnect broadcast. Rendered with parse_mode=HTML.
    'migration' => "🔄 <b>We've moved to a new server</b>\n\n"
        . "Your VPN stopped connecting because our previous server was blocked. "
        . "Tap <b>Open App</b> below and press <b>Connect</b> to get back online in a few seconds.\n\n"
        . "If the app won't open, copy the config below and add it manually in V2RayTun or Hiddify.",

    'manual_config' => '👇 Manual config — tap to copy, then paste into your app:',

    'open_app_button' => '🚀 Open App',
];
