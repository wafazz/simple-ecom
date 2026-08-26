<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\IntegrationCredentialRequest;
use App\Services\EasyParcelService;
use App\Services\ToyyibPayService;
use App\Support\IntegrationConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** REQ-006 — the admin authorises EasyParcel once (Planning §11.B.3). */
class IntegrationController extends Controller
{
    private const STATE_KEY = 'easyparcel_oauth_state';

    public function __construct(
        private readonly EasyParcelService $easyparcel,
        private readonly ToyyibPayService $toyyibpay,
    ) {}

    public function index(): View
    {
        $token = $this->easyparcel->token();

        return view('admin.integrations', [
            'configured' => $this->easyparcel->isConfigured(),
            'connected' => $this->easyparcel->isConnected(),
            // Expiry dates and connection state only — never token material.
            'expiresAt' => $token?->expires_at,
            'connectedAt' => $token?->connected_at,
            'toyyibpayConfigured' => $this->toyyibpay->isConfigured(),
            'sandbox' => [
                'toyyibpay' => (bool) config('services.toyyibpay.sandbox'),
                'easyparcel' => (bool) config('services.easyparcel.sandbox'),
            ],
            // Presence, source and a masked hint — never the value itself.
            'credentials' => collect(IntegrationConfig::EDITABLE)
                ->mapWithKeys(fn (string $key): array => [$key => [
                    'source' => IntegrationConfig::source($key),
                    'hint' => IntegrationConfig::hint($key),
                    'write_only' => in_array($key, IntegrationConfig::WRITE_ONLY, true),
                ]])
                ->all(),
        ]);
    }

    /** REQ-011 — save admin-entered credentials, encrypted at rest. */
    public function storeCredentials(IntegrationCredentialRequest $request): RedirectResponse
    {
        $submitted = $request->credentials();

        if ($submitted === []) {
            return back()->with('status', 'Nothing to save — all fields were blank.');
        }

        foreach ($submitted as $key => $value) {
            IntegrationConfig::put($key, $value);
        }

        // Keys only. A credential must never reach a log line (spec §24).
        Log::info('Integration credentials updated', [
            'keys' => array_keys($submitted),
            'user_id' => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.integrations.index')
            ->with('status', count($submitted).' credential(s) saved.');
    }

    /**
     * Removes the stored value so the `.env` setting takes over again. A blank
     * field on save cannot mean this — the form never shows the current value,
     * so "blank" has to mean "leave it alone".
     */
    public function clearCredential(Request $request, string $key): RedirectResponse
    {
        if (! in_array($key, IntegrationConfig::EDITABLE, true)) {
            abort(404);
        }

        IntegrationConfig::forget($key);

        Log::warning('Integration credential cleared', [
            'key' => $key,
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('status', 'Cleared. The value from .env applies again, if one is set.');
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
