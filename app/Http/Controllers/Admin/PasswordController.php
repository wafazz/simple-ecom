<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** REQ-009 — Planning §17.4. */
class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.password', [
            'forced' => (bool) $request->user()->must_change_password,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ])->save();

        // Any other session for this account is invalidated.
        Auth::logoutOtherDevices($data['password']);
        $request->session()->regenerate();

        Log::info('Admin changed their password', ['user_id' => $request->user()->id]);

        return redirect()->route('admin.dashboard')->with('status', 'Password updated.');
    }
}
