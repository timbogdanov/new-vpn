<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#0A0A0A" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#FFFFFF" media="(prefers-color-scheme: light)">

    <title>{{ config('app.name') }}</title>

    {{-- Geist (UI, Latin + Cyrillic) + Geist Mono (numeric) + Instrument Serif (display, Latin only).
         Served via Bunny Fonts for WebView reliability and GDPR. --}}
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link
        rel="stylesheet"
        href="https://fonts.bunny.net/css?family=geist:400,500,600|geist-mono:400,500|instrument-serif:400,400i&display=swap"
    >

    <script src="https://telegram.org/js/telegram-web-app.js"></script>

    {{-- Paint theme vars BEFORE the SPA bundle to kill any FOUC --}}
    <script>
        (function () {
            try {
                var tg = window.Telegram && window.Telegram.WebApp;
                if (!tg) return;
                var tp = tg.themeParams || {};
                var root = document.documentElement;
                var map = {
                    bg_color: '--tg-theme-bg-color',
                    text_color: '--tg-theme-text-color',
                    hint_color: '--tg-theme-hint-color',
                    link_color: '--tg-theme-link-color',
                    button_color: '--tg-theme-button-color',
                    button_text_color: '--tg-theme-button-text-color',
                    secondary_bg_color: '--tg-theme-secondary-bg-color',
                    header_bg_color: '--tg-theme-header-bg-color',
                    bottom_bar_bg_color: '--tg-theme-bottom-bar-bg-color',
                    accent_text_color: '--tg-theme-accent-text-color',
                    section_bg_color: '--tg-theme-section-bg-color',
                    section_header_text_color: '--tg-theme-section-header-text-color',
                    section_separator_color: '--tg-theme-section-separator-color',
                    subtitle_text_color: '--tg-theme-subtitle-text-color',
                    destructive_text_color: '--tg-theme-destructive-text-color'
                };
                for (var k in map) {
                    if (tp[k]) root.style.setProperty(map[k], tp[k]);
                }
                root.setAttribute('data-theme', tg.colorScheme || 'dark');
                if (tg.viewportStableHeight) {
                    root.style.setProperty('--tg-viewport-stable-height', tg.viewportStableHeight + 'px');
                }
                try { tg.expand(); } catch (_) {}
            } catch (_) {}
        })();
    </script>

    @vite(['resources/css/miniapp.css', 'resources/js/miniapp/main.js'])
</head>
<body>
    <div id="app" data-init-data="{{ '' }}"></div>
</body>
</html>
