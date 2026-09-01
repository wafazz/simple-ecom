<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin correction of an order's delivery and contact details.
 *
 * The same seven fields as CheckoutRequest, under the same rules — a postcode
 * the checkout would have rejected must not become valid just because an admin
 * typed it. Deliberately NOT extending CheckoutRequest: that carries
 * shipping_service_id, and a shipping field on this form would imply the fee
 * gets re-quoted, which it does not.
 *
 * Nothing here touches money, totals or line items. They are absent from the
 * rules AND from Order::$fillable, so neither half can be reached from a
 * request even if the other were changed.
 */
class OrderDetailsRequest extends FormRequest
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
            // ISO 3166-2:MY code, not a free-text name — EasyParcel will not
            // accept anything else, and a booking made later reads this row.
            'state' => ['required', 'string', Rule::in(array_keys(config('shop.states')))],
            'postcode' => ['required', 'string', 'regex:/^\d{5}$/'],
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
        $this->merge(['postcode' => trim((string) $this->input('postcode'))]);
    }
}
