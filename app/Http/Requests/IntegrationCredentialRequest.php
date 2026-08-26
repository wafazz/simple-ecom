<?php

namespace App\Http\Requests;

use App\Support\IntegrationConfig;
use Illuminate\Foundation\Http\FormRequest;

/**
 * REQ-011 — one provider's credentials.
 *
 * Each provider has its own form, so a request carries only that provider's
 * fields and can only ever write that provider's keys.
 *
 * Every field is optional: blank means "leave the stored value alone". The form
 * never renders a secret back, so blank cannot be read as "clear it" — clearing
 * is a separate, explicit action.
 */
class IntegrationCredentialRequest extends FormRequest
{
    /** field name => credential key */
    private const FIELDS = [
        'toyyibpay' => [
            'toyyibpay_secret_key' => 'toyyibpay.secret_key',
            'toyyibpay_category_code' => 'toyyibpay.category_code',
        ],
        'easyparcel' => [
            'easyparcel_client_id' => 'easyparcel.client_id',
            'easyparcel_client_secret' => 'easyparcel.client_secret',
        ],
    ];

    public function authorize(): bool
    {
        return in_array($this->provider(), IntegrationConfig::PROVIDERS, true);
    }

    public function rules(): array
    {
        $rules = [];

        foreach (self::FIELDS[$this->provider()] ?? [] as $field => $key) {
            $rules[$field] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    public function provider(): string
    {
        return (string) $this->route('provider');
    }

    /**
     * Submitted values keyed by credential, skipping the blanks. Fields for
     * another provider are ignored even if posted.
     *
     * @return array<string, string>
     */
    public function credentials(): array
    {
        $out = [];

        foreach (self::FIELDS[$this->provider()] ?? [] as $field => $key) {
            $value = trim((string) $this->input($field, ''));

            if ($value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
