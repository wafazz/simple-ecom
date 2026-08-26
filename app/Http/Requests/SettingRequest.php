<?php

namespace App\Http\Requests;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * REQ-011 — Planning §12.2.
 *
 * NON-SECRET values only. API credentials live in .env and are never editable
 * here, never rendered into a field, and never sent to Blade (spec §16).
 */
class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'max:255'],
            'store_email' => ['required', 'string', 'email', 'max:255'],
            'store_phone' => ['required', 'string', 'max:32'],
            'currency' => ['required', 'string', 'size:3'],

            // Quotation origin. ISO 3166-2:MY — a wrong origin means a wrong
            // rate on every order (OQ-02).
            'pickup_postcode' => ['required', 'string', 'regex:/^\d{5}$/'],
            'pickup_state' => ['required', 'string', Rule::in(array_keys(config('shop.states')))],

            'default_weight_g' => ['required', 'integer', 'min:1', 'max:100000'],
            'flat_shipping_fee' => ['required', 'numeric', 'min:0', 'max:100000'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:10000'],

            // No ad spend exists in the order data, so ROAS has no source
            // unless the admin supplies one. 0 means "not tracked".
            'ads_cost' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'pickup_postcode.regex' => 'Enter a 5-digit Malaysian postcode.',
            'pickup_state.in' => 'Choose a state from the list.',
        ];
    }

    /** Ringgit -> sen. The single conversion point for this form. */
    public function flatShippingFeeMinor(): int
    {
        return Money::fromDecimalString((string) $this->input('flat_shipping_fee'));
    }

    public function adsCostMinor(): int
    {
        return Money::fromDecimalString((string) $this->input('ads_cost'));
    }
}
