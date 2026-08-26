<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Planning §13.3 — lazy loads, missing attributes and silently discarded
        // attributes fail loudly in dev and tests instead of becoming N+1
        // queries in production.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Planning §14 — the ToyyibPay callback requires HTTPS, and route()
        // must generate it as such.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
