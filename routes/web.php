<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\VariationController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderStatusController;
use App\Http\Controllers\ProductController;
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

// Checkout (REQ-004)
Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/confirmation/{orderNo}', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');

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

    Route::middleware(['auth', 'admin.active'])->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
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

        // Variations + stock (REQ-002, REQ-008)
        // The param is {variant}, not {variation}: a custom key turns on scoped
        // bindings, and Laravel resolves the child via Product::variants().
        Route::get('/products/{product:id}/variations', [VariationController::class, 'index'])->name('products.variations.index');
        Route::post('/products/{product:id}/variations', [VariationController::class, 'store'])->name('products.variations.store');
        Route::put('/products/{product:id}/variations/{variant:id}', [VariationController::class, 'update'])->name('products.variations.update');
        Route::patch('/products/{product:id}/variations/{variant:id}/stock', [VariationController::class, 'updateStock'])->name('products.variations.stock');
    });
});
