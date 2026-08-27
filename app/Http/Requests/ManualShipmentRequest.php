<?php

namespace App\Http\Requests;

use App\Enums\Courier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Manual fulfilment — courier, AWB number and the label file. */
class ManualShipmentRequest extends FormRequest
{
    /** The admin group already gates this route; nothing extra to authorise. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'courier' => ['required', Rule::enum(Courier::class)],

            // 64 matches shipments.awb_no. The character class keeps a pasted
            // tracking URL or a stray quote out of a column that is printed on
            // a label and searched on the orders screen.
            'awb_no' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9\-\/]*$/'],

            // Optional: the number is the useful half. A shop that only has a
            // paper label should still be able to record what it dispatched.
            //
            // mimes: validated by CONTENT, not by the client's filename or the
            // Content-Type header it sent.
            'awb_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'awb_no.regex' => 'The AWB number may only contain letters, numbers, hyphens and slashes.',
            'awb_file.mimes' => 'The AWB must be a PDF, JPG or PNG file.',
            'awb_file.max' => 'The AWB file may not be larger than 8 MB.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'awb_no' => 'AWB number',
            'awb_file' => 'AWB file',
        ];
    }
}
