<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * REQ-004 — Planning §9.1.
 *
 * Customer and address only. Prices, totals and the shipping fee are NEVER
 * accepted from the request — they are computed server-side at order creation
 * (spec §17).
 */
class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'string', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:32'],

            'address_line' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            // Must be an ISO 3166-2:MY code, not a free-text name — the
            // EasyParcel quotation API will not accept anything else (§11.B.1).
            'state' => ['required', 'string', Rule::in(array_keys(config('shop.states')))],
            'postcode' => ['required', 'string', 'regex:/^\d{5}$/'],
            'country' => ['required', 'string', 'size:2'],
        ];
    }

    public function messages(): array
    {
        return [
            'postcode.regex' => 'Enter a 5-digit Malaysian postcode.',
            'state.in' => 'Choose a state from the list.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country' => strtoupper((string) ($this->input('country') ?: 'MY')),
            'postcode' => trim((string) $this->input('postcode')),
        ]);
    }
}
