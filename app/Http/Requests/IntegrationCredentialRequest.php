<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * REQ-011 — integration credentials.
 *
 * Every field is optional: a blank field means "leave the stored value alone".
 * The form never renders a secret back, so a blank input cannot be read as
 * "clear it" — clearing is a separate, explicit action.
 */
class IntegrationCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'toyyibpay_secret_key' => ['nullable', 'string', 'max:255'],
            'toyyibpay_category_code' => ['nullable', 'string', 'max:64'],
            'easyparcel_client_id' => ['nullable', 'string', 'max:255'],
            'easyparcel_client_secret' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Submitted values keyed by credential, skipping the blanks.
     *
     * @return array<string, string>
     */
    public function credentials(): array
    {
        $map = [
            'toyyibpay_secret_key' => 'toyyibpay.secret_key',
            'toyyibpay_category_code' => 'toyyibpay.category_code',
            'easyparcel_client_id' => 'easyparcel.client_id',
            'easyparcel_client_secret' => 'easyparcel.client_secret',
        ];

        $out = [];

        foreach ($map as $field => $key) {
            $value = trim((string) $this->input($field, ''));

            if ($value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
