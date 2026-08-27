<?php

namespace App\Http\Requests;

use App\Models\Slide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SlideRequest extends FormRequest
{
    /** The admin group already gates these routes. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Validated by CONTENT, not by the filename or the Content-Type the
            // browser claimed. Recommended artwork is 2400x1000.
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=1200'],
            'remove_image' => ['nullable', 'boolean'],

            'focal' => ['required', Rule::in(array_keys(Slide::FOCAL))],
            'eyebrow' => ['nullable', 'string', 'max:80'],
            'headline' => ['required', 'string', 'max:120'],
            'subtext' => ['nullable', 'string', 'max:300'],

            // Relative paths ("/products") and absolute links both work; the
            // pair must be complete or the button is not rendered at all.
            'cta_label' => ['nullable', 'string', 'max:40', 'required_with:cta_url'],
            'cta_url' => ['nullable', 'string', 'max:255', 'required_with:cta_label'],
            'cta2_label' => ['nullable', 'string', 'max:40', 'required_with:cta2_url'],
            'cta2_url' => ['nullable', 'string', 'max:255', 'required_with:cta2_label'],

            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'image.dimensions' => 'The banner must be at least 1200 pixels wide — 2400 × 1000 is the recommended size.',
            'image.max' => 'The banner may not be larger than 2 MB. Export it as JPEG or WebP at about 80% quality.',
            'image.mimes' => 'The banner must be a JPG, PNG or WebP file.',
            'cta_label.required_with' => 'A button needs a label as well as a link.',
            'cta_url.required_with' => 'A button needs a link as well as a label.',
            'cta2_label.required_with' => 'The second button needs a label as well as a link.',
            'cta2_url.required_with' => 'The second button needs a link as well as a label.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'cta_label' => 'button label', 'cta_url' => 'button link',
            'cta2_label' => 'second button label', 'cta2_url' => 'second button link',
        ];
    }

    /** Checkboxes are absent when unticked; the model needs a real false. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'remove_image' => $this->boolean('remove_image'),
        ]);
    }
}
