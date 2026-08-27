<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The return & exchange policy, as plain text. */
class ReturnPolicyRequest extends FormRequest
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
            // Optional: an empty policy unpublishes the page rather than
            // serving a blank one.
            'return_policy' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['return_policy' => 'policy'];
    }

    /** The stored text, normalised. Empty means unpublished. */
    public function body(): string
    {
        // Windows line endings would otherwise survive into the paragraph
        // split and the character count.
        return trim(str_replace("\r\n", "\n", (string) $this->input('return_policy')));
    }
}
