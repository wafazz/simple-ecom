<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * REQ-007 — Planning §14.
 *
 * The email is not decoration: it is the authorisation check. Order numbers are
 * sequential and guessable, so requiring the matching email is what stops one
 * customer reading another's order.
 */
class OrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_no' => ['required', 'string', 'max:32'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'Enter the email address you used at checkout.',
        ];
    }
}
