<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * REQ-009 / REQ-010 — Planning §17.4.
 *
 * A seeded or handed-over credential must not survive first use. Until the
 * admin sets their own password, every admin screen redirects to the change
 * form — the logout route excepted, so they are never trapped.
 */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user?->must_change_password
            && ! $request->routeIs('admin.password.*', 'admin.logout')) {
            return redirect()->route('admin.password.edit');
        }

        return $next($request);
    }
}
