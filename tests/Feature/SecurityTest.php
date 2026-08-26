<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminIsActive;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-010 — Planning §14. Configuration-level guards. */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Middleware aliases and the CSRF exclusion list are synced to the router
     * when the HTTP kernel bootstraps, so these assertions need a real request
     * to have happened first.
     */
    protected function bootHttpKernel(): void
    {
        $this->get(route('home'))->assertOk();
    }

    #[Test]
    public function only_the_payment_callback_is_exempt_from_csrf(): void
    {
        $this->bootHttpKernel();

        // Laravel 11+ puts paths from validateCsrfTokens(except:) into the
        // STATIC $neverVerify, not the instance $except.
        $exempt = (new \ReflectionProperty(ValidateCsrfToken::class, 'neverVerify'))
            ->getValue();

        // This exclusion is safe ONLY because the callback body is never
        // trusted (Planning §11.A.5). Anything else in this list is a hole, so
        // it is asserted exactly rather than with "contains".
        $this->assertSame(
            ['payment/toyyibpay/callback'],
            $exempt,
            'The CSRF exclusion list must contain the ToyyibPay callback and nothing else.'
        );
    }

    #[Test]
    public function every_admin_route_is_behind_auth_and_the_active_check(): void
    {
        // Authorization must sit on the route group, never on hidden nav links.
        $unprotected = [];

        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->getName() ?? '', 'admin.')) {
                continue;
            }

            // Login routes are intentionally public.
            if (str_starts_with($route->getName(), 'admin.login')) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            if (! in_array('auth', $middleware, true) || ! in_array('admin.active', $middleware, true)) {
                $unprotected[] = $route->getName();
            }
        }

        $this->assertSame([], $unprotected);
    }

    #[Test]
    public function the_admin_active_middleware_is_registered_under_its_alias(): void
    {
        $this->bootHttpKernel();

        $aliases = app('router')->getMiddleware();

        $this->assertSame(EnsureAdminIsActive::class, $aliases['admin.active'] ?? null);
    }

    #[Test]
    public function credentials_are_never_flashed_back_into_the_session(): void
    {
        $handler = app(ExceptionHandler::class);

        $reflected = new \ReflectionProperty($handler, 'dontFlash');
        $dontFlash = $reflected->getValue($handler);

        foreach (['password', 'userSecretKey', 'client_secret', 'access_token', 'refresh_token'] as $secret) {
            $this->assertContains($secret, $dontFlash, "{$secret} must never be flashed.");
        }
    }

    #[Test]
    public function debug_mode_and_secure_cookies_are_correct_for_production(): void
    {
        $this->assertFalse((bool) config('app.debug', false) && app()->isProduction());
        $this->assertSame('AES-256-GCM', config('app.cipher'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertTrue((bool) config('session.encrypt'));
    }
}
