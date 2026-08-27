<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\MailController;
use App\Http\Controllers\Admin\ManualShipmentController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PolicyController as AdminPolicyController;
use App\Http\Controllers\Admin\PasswordController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ShipmentController;
use App\Http\Controllers\Admin\SlideController;
use App\Http\Controllers\Admin\VariationController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderStatusController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShippingController;
use App\Support\IntegrationConfig;
use Illuminate\Support\Facades\Route;

/*
| Spec §21. Static literals before parameterised routes; route model binding
| where it makes the code clearer. Phases 5–9 add catalogue, cart, checkout,
| payment, shipping and the rest of the admin panel.
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

// Static literals before parameterised routes (spec §21).
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');

// Cart (REQ-003)
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
Route::patch('/cart/{variant}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{variant}', [CartController::class, 'destroy'])->name('cart.destroy');

// Shipping rates (REQ-006) — AJAX from the checkout page.
Route::post('/shipping/quote', [ShippingController::class, 'quote'])->name('shipping.quote');

// Checkout (REQ-004)
Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/confirmation/{orderNo}', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');

// Payment (REQ-005). The return and callback URLs are UNTRUSTED — both take
// only the bill code and re-query the gateway server-side (Planning §11.A.5).
Route::get('/payment/toyyibpay/return', [PaymentController::class, 'handleReturn'])->name('payment.return');
Route::post('/payment/toyyibpay/callback', [PaymentController::class, 'handleCallback'])->name('payment.callback');
Route::get('/payment/{orderNo}', [PaymentController::class, 'pay'])->name('payment.pay');

Route::get('/return-policy', [PolicyController::class, 'returns'])->name('policy.returns');

Route::get('/order-status', [OrderStatusController::class, 'show'])->name('order-status.show');
Route::post('/order-status', [OrderStatusController::class, 'lookup'])->name('order-status.lookup');

/*
| Admin. Everything except login sits behind auth + admin.active, applied to the
| GROUP — not to individual actions, and never by hiding nav links (Planning §14).
*/
Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');

        // throttle replaces a hand-rolled attempt counter (spec §16).
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('login.attempt');
    });

    Route::middleware(['auth', 'admin.active', 'admin.password'])->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        // A seeded or handed-over credential must not survive first use
        // (Planning §17.4).
        Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
        Route::put('/password', [PasswordController::class, 'update'])->name('password.update');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Categories (REQ-001)
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::patch('/categories/{category}/toggle', [CategoryController::class, 'toggle'])->name('categories.toggle');

        // Products (REQ-001)
        // {product:id} is explicit: Product::getRouteKeyName() is 'slug' for
        // pretty storefront URLs, and admin URLs must not change when a product
        // is renamed.
        Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
        Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product:id}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product:id}', [AdminProductController::class, 'update'])->name('products.update');
        Route::patch('/products/{product:id}/toggle', [AdminProductController::class, 'toggle'])->name('products.toggle');

        // Home page banners.
        Route::get('/slides', [SlideController::class, 'index'])->name('slides.index');
        Route::get('/slides/create', [SlideController::class, 'create'])->name('slides.create');
        Route::post('/slides', [SlideController::class, 'store'])->name('slides.store');
        Route::get('/slides/{slide}/edit', [SlideController::class, 'edit'])->name('slides.edit');
        Route::put('/slides/{slide}', [SlideController::class, 'update'])->name('slides.update');
        Route::patch('/slides/{slide}/toggle', [SlideController::class, 'toggle'])->name('slides.toggle');
        Route::delete('/slides/{slide}', [SlideController::class, 'destroy'])->name('slides.destroy');

        // Orders (REQ-007)
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        // ⚠ EVERY literal /orders/<word> route must be registered BEFORE the
        // {order} wildcard below. Laravel matches in registration order, so a
        // wildcard declared first swallows "book" as an order id and the page
        // 404s on a model that does not exist.

        // One action for the row button and the bulk bar alike (REQ-007).
        Route::patch('/orders/process', [OrderController::class, 'process'])->name('orders.process');

        // The bulk bar posts here and is dispatched to the right action.
        Route::post('/orders/bulk', [OrderController::class, 'bulk'])->name('orders.bulk');

        // REQ-013 — booking spends real courier credit.
        //
        // The GET only QUOTES and shows the confirmation screen; it charges
        // nothing, so it is safe to link to. The POST is what spends money and
        // is never reachable by a link, a prefetch or a crawler.
        Route::get('/orders/book', [ShipmentController::class, 'create'])->name('orders.book');
        Route::post('/orders/book', [ShipmentController::class, 'store'])->name('orders.book.store');

        // Read-only: lists the AWBs already issued for printing.
        Route::get('/orders/awb', [ShipmentController::class, 'labels'])->name('orders.awb');

        // Manual fulfilment, for while EasyParcel is on hold. Spends nothing
        // and calls no third party — it records what the admin already did.
        Route::post('/orders/{order:id}/awb', [ManualShipmentController::class, 'store'])
            ->name('orders.awb.store');

        // An uploaded label is customer PII (name, address, phone), so it is
        // never a public file — it is streamed from a private disk through
        // this authenticated route.
        Route::get('/orders/{order:id}/awb/label', [ManualShipmentController::class, 'label'])
            ->name('orders.awb.label');

        Route::get('/orders/{order:id}', [OrderController::class, 'show'])->name('orders.show');
        Route::patch('/orders/{order:id}/status', [OrderController::class, 'updateStatus'])->name('orders.status');

        Route::patch('/orders/{order:id}/refund', [OrderController::class, 'markRefunded'])->name('orders.refund');

        // Row actions for an order that has not been paid for.
        Route::patch('/orders/{order:id}/approve', [OrderController::class, 'approve'])->name('orders.approve');
        Route::patch('/orders/{order:id}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::delete('/orders/{order:id}', [OrderController::class, 'destroy'])->name('orders.destroy');

        // The shop's own writing, kept off the Settings screen — see
        // Admin\PolicyController. The text still lives in `settings`.
        Route::get('/return-policy', [AdminPolicyController::class, 'edit'])->name('policy.edit');
        Route::put('/return-policy', [AdminPolicyController::class, 'update'])->name('policy.update');

        // Mailgun over SMTP, on its own screen.
        Route::get('/mail', [MailController::class, 'edit'])->name('mail.edit');
        Route::put('/mail', [MailController::class, 'update'])->name('mail.update');

        // Throttled: each press is a real message leaving the server.
        Route::post('/mail/test', [MailController::class, 'test'])
            ->middleware('throttle:10,1')
            ->name('mail.test');

        // Settings (REQ-011)
        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

        // EasyParcel connection (REQ-006)
        Route::get('/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
        // One form per provider, so a submission can only ever write that
        // provider's keys.
        Route::put('/integrations/{provider}/credentials', [IntegrationController::class, 'storeCredentials'])
            ->whereIn('provider', IntegrationConfig::PROVIDERS)
            ->name('integrations.credentials');
        Route::delete('/integrations/credentials/{key}', [IntegrationController::class, 'clearCredential'])
            ->where('key', '[a-z_.]+')->name('integrations.credentials.clear');

        // Only providers whose environment WE select — see MODE_SELECTABLE.
        Route::patch('/integrations/{provider}/mode', [IntegrationController::class, 'setMode'])
            ->whereIn('provider', IntegrationConfig::MODE_SELECTABLE)
            ->name('integrations.mode');

        // Throttled: each call is a real outbound request to a third party.
        Route::post('/integrations/{provider}/test', [IntegrationController::class, 'testConnection'])
            ->whereIn('provider', IntegrationConfig::PROVIDERS)
            ->middleware('throttle:10,1')
            ->name('integrations.test');
        Route::post('/integrations/easyparcel/connect', [IntegrationController::class, 'connect'])->name('integrations.connect');
        Route::get('/integrations/easyparcel/callback', [IntegrationController::class, 'callback'])->name('integrations.callback');
        Route::delete('/integrations/easyparcel', [IntegrationController::class, 'disconnect'])->name('integrations.disconnect');

        // Stock (REQ-008). Variants are DEFINED on the product form; this screen
        // exists only for the day-to-day job of adjusting quantities.
        // The param is {variant}, not {variation}: a custom key turns on scoped
        // bindings, and Laravel resolves the child via Product::variants().
        Route::get('/products/{product:id}/stock', [VariationController::class, 'index'])->name('products.variations.index');
        Route::patch('/products/{product:id}/stock/{variant:id}', [VariationController::class, 'updateStock'])->name('products.variations.stock');
    });
});
