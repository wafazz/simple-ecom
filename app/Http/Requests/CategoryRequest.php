<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** REQ-001 */
class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'alpha_dash',
                // Ignore self on update, otherwise editing a category always
                // collides with its own slug.
                Rule::unique('categories', 'slug')->ignore($this->route('category')),
            ],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => filled($this->input('slug'))
                ? str($this->input('slug'))->slug()->value()
                : str((string) $this->input('name'))->slug()->value(),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
