<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EasyParcelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** REQ-006 — the admin authorises EasyParcel once (Planning §11.B.3). */
class IntegrationController extends Controller
{
    private const STATE_KEY = 'easyparcel_oauth_state';

    public function __construct(private readonly EasyParcelService $easyparcel) {}

    public function index(): View
    {
        $token = $this->easyparcel->token();

        return view('admin.integrations', [
            'configured' => $this->easyparcel->isConfigured(),
            'connected' => $this->easyparcel->isConnected(),
            // Expiry dates and connection state only — never token material.
            'expiresAt' => $token?->expires_at,
            'connectedAt' => $token?->connected_at,
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        if (! $this->easyparcel->isConfigured()) {
            return back()->with('error', 'Set EASYPARCEL_CLIENT_ID and EASYPARCEL_CLIENT_SECRET first.');
        }

        $state = Str::random(40);
        $request->session()->put(self::STATE_KEY, $state);

        return redirect()->away(
            $this->easyparcel->authorizationUrl(route('admin.integrations.callback'), $state)
        );
    }

    public function callback(Request $request): RedirectResponse
    {
        $expected = (string) $request->session()->pull(self::STATE_KEY, '');
        $received = (string) $request->query('state', '');

        // Without this check an attacker could feed us an authorization code
        // for THEIR account and the store would ship on their credit.
        if ($expected === '' || ! hash_equals($expected, $received)) {
            Log::warning('EasyParcel OAuth state mismatch — authorisation rejected');

            return redirect()
                ->route('admin.integrations.index')
                ->with('error', 'Authorisation could not be verified. Please try again.');
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            return redirect()
                ->route('admin.integrations.index')
                ->with('error', 'EasyParcel did not return an authorisation code.');
        }

        try {
            $this->easyparcel->exchangeAuthorizationCode($code, route('admin.integrations.callback'));
        } catch (\Throwable $e) {
            Log::error('EasyParcel token exchange failed', ['error' => $e->getMessage()]);

            return redirect()
                ->route('admin.integrations.index')
                ->with('error', 'Could not complete the connection. Please try again.');
        }

        return redirect()
            ->route('admin.integrations.index')
            ->with('status', 'EasyParcel connected.');
    }

    public function disconnect(): RedirectResponse
    {
        $this->easyparcel->disconnect();

        return redirect()
            ->route('admin.integrations.index')
            ->with('status', 'EasyParcel disconnected.');
    }
}
