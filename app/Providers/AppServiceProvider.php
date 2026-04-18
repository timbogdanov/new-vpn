<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Telescope only loads in local; never in prod.
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }
    }

    public function boot(): void
    {
        // Force HTTPS on the public URL so paywall + sub URLs render with the
        // right scheme behind Dokploy's TLS-terminating proxy.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->registerFeatureFlags();
    }

    private function registerFeatureFlags(): void
    {
        if (!class_exists(Feature::class)) {
            return;
        }

        Feature::define('bento_home', fn () => true);
        Feature::define('lottie_animations', fn () => true);
        Feature::define('quota_enforcement_hard_cutoff', fn () => true);
    }
}
