<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * REQ-009 — Planning §14.
 *
 * Laravel's standard auth guard on the default users table. No starter kit and
 * no auth package (spec §16). Rate limiting is the `throttle` middleware on the
 * route, not a hand-rolled attempt counter.
 */
class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('admin.login');
    }

    public function login(AdminLoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        // is_active is part of the credentials, so a deactivated admin cannot
        // authenticate at all rather than being bounced afterwards.
        if (! Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            Log::warning('Failed admin login', ['email' => $credentials['email']]);

            // One generic message: saying "no such account" would confirm which
            // addresses exist.
            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        // Fixation defence: the pre-login session id must not survive login.
        $request->session()->regenerate();

        Log::info('Admin logged in', ['user_id' => Auth::id()]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
