<?php

namespace App\Providers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Setting;
use App\Services\CartService;
use App\Services\EasyParcelService;
use App\Services\ToyyibPayService;
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

        // Bound explicitly: the constructor takes plain strings from config,
        // which the container cannot autowire.
        $this->app->bind(ToyyibPayService::class, fn () => ToyyibPayService::fromConfig());
        $this->app->bind(EasyParcelService::class, fn () => EasyParcelService::fromConfig());
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
        // Admin-only, so the storefront never pays for this query. One
        // indexed count; paid orders that could not be stocked must be visible
        // from every admin screen, not just the order list (Planning §7.5).
        // Registered for the admin views too, not just the layout: a child
        // view that renders the variable in its own section receives nothing
        // from a layout-only composer. (Repeat of a Phase 4 defect.)
        View::composer(['layouts.admin', 'admin.*'], function ($view): void {
            // ONE grouped query for every sidebar badge, rather than a count
            // per status. Missing keys mean zero.
            $counts = Order::query()
                ->selectRaw('order_status, count(*) as total')
                ->groupBy('order_status')
                ->pluck('total', 'order_status')
                ->all();

            $view->with([
                'orderStatusCounts' => $counts,
                'orderTotalCount' => array_sum($counts),
                'needsReviewCount' => $counts[OrderStatus::NeedsReview->value] ?? 0,
            ]);
        });

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
