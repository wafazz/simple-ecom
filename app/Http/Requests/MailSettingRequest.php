<?php

namespace App\Http\Requests;

use App\Support\MailSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MailSettingRequest extends FormRequest
{
    /** The admin group already gates this route. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'mailgun_smtp_host' => ['required', 'string', 'max:255'],
            'mailgun_smtp_port' => ['required', Rule::in(array_keys(MailSettings::PORTS))],

            // Mailgun's SMTP login, e.g. postmaster@mg.your-domain.com
            'smtp_username' => ['nullable', 'string', 'max:255'],

            // Blank means "leave the stored one alone" — the form never shows
            // it back, so blank cannot mean "clear it". Clearing is its own
            // action, as with every other credential here.
            'smtp_password' => ['nullable', 'string', 'max:255'],

            // Mailgun rejects a sender outside the verified domain, so this is
            // checked as an address before it can fail as a send.
            'mail_from_address' => ['required', 'email', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'mailgun_smtp_host' => 'SMTP host',
            'mailgun_smtp_port' => 'SMTP port',
            'smtp_username' => 'SMTP username',
            'smtp_password' => 'SMTP password',
            'mail_from_address' => 'sender address',
            'mail_from_name' => 'sender name',
        ];
    }
}
