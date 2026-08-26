<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureAdminIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            AssignRequestId::class,
        ]);

        $middleware->alias([
            'admin.active' => EnsureAdminIsActive::class,
        ]);

        // Planning §11.A.4 — ToyyibPay carries no CSRF token. This exclusion is
        // safe ONLY because the callback body is never trusted: the handler
        // takes the bill code and re-queries the gateway server-side (§11.A.5).
        $middleware->validateCsrfTokens(except: [
            'payment/toyyibpay/callback',
        ]);

        $middleware->redirectGuestsTo(fn () => route('admin.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Planning §14/§15 — belt and braces with #[\SensitiveParameter].
        // Credentials must never reach a log line or an error page.
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'userSecretKey',
            'client_secret',
            'access_token',
            'refresh_token',
        ]);
    })->create();
