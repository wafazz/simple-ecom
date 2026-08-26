<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * REQ-009 / REQ-010 — Planning §14.
 *
 * Deactivating an admin must take effect immediately, not whenever their
 * session happens to expire. The `auth` middleware only proves they logged in
 * at some point; this proves they are still allowed in now.
 */
class EnsureAdminIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() || ! Auth::user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('admin.login')
                ->withErrors(['email' => 'This account is no longer active.']);
        }

        return $next($request);
    }
}
