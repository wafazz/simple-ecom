<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderStatusController;
use Illuminate\Support\Facades\Route;

/*
| Spec §21. Static literals before parameterised routes; route model binding
| where it makes the code clearer. Phases 5–9 add catalogue, cart, checkout,
| payment, shipping and the rest of the admin panel.
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

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
    });
});
