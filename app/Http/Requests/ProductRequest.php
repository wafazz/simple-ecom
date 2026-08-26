<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** REQ-001 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'alpha_dash',
                Rule::unique('products', 'slug')->ignore($this->route('product')),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            // Never trust the client filename or the reported mime alone
            // (Planning §14). The stored name is framework-generated.
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
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
