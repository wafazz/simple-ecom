<?php

namespace App\Providers;

use App\Models\Setting;
use App\Services\CartService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(
            CartService::class,
            fn ($app) => new CartService($app['session.store'])
        );
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

        // Store identity is needed by layouts AND by page views that render it
        // in their own sections. Registering on 'layouts.*' alone left child
        // views with an undefined $storeName. Settings are cached, so this is
        // not a query per view.
        View::composer(['layouts.*', 'storefront.*', 'admin.*'], function ($view): void {
            $view->with([
                'storeName' => Setting::get('store_name'),
                'currency' => Setting::get('currency'),
                'currencySymbol' => config('shop.currency_symbol'),
                'cartCount' => app(CartService::class)->count(),
            ]);
        });
    }
}
