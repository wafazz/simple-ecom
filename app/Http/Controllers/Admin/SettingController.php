<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettingRequest;
use App\Models\Setting;
use App\Services\EasyParcelService;
use App\Services\ToyyibPayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/** REQ-011 */
class SettingController extends Controller
{
    public function __construct(
        private readonly ToyyibPayService $toyyibpay,
        private readonly EasyParcelService $easyparcel,
    ) {}

    public function edit(): View
    {
        return view('admin.settings', [
            'settings' => Setting::cached(),
            // Booleans only. The screen must be able to say whether a credential
            // is present without ever handling its value (spec §16).
            'toyyibpayConfigured' => $this->toyyibpay->isConfigured(),
            'easyparcelConfigured' => $this->easyparcel->isConfigured(),
            'easyparcelConnected' => $this->easyparcel->isConnected(),
            'sandbox' => (bool) config('services.toyyibpay.sandbox'),
        ]);
    }

    public function update(SettingRequest $request): RedirectResponse
    {
        $values = $request->safe()->except(['flat_shipping_fee', 'ads_cost']);
        $values['flat_shipping_fee_minor'] = (string) $request->flatShippingFeeMinor();
        $values['ads_cost_minor'] = (string) $request->adsCostMinor();

        foreach ($values as $key => $value) {
            Setting::put($key, (string) $value);
        }

        Log::info('Store settings updated', ['user_id' => $request->user()?->id]);

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', 'Settings saved.');
    }
}
