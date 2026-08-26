<?php

namespace App\Http\Requests;

use App\Enums\VariantStatus;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * REQ-002 / REQ-008 — Planning §7.1.
 *
 * Price is taken in ringgit for the admin's convenience and converted to sen
 * exactly once, here. Nothing downstream ever sees a decimal price.
 */
class VariationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku' => [
                'required', 'string', 'max:64',
                Rule::unique('product_variants', 'sku')->ignore($this->route('variant')),
            ],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'stock_qty' => ['required', 'integer', 'min:0'],
            'weight_g' => ['required', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', Rule::enum(VariantStatus::class)],

            // Nullable in the FORM, coerced to '' before it reaches the column.
            // A NULL here would let two "no-option" variants coexist on one
            // product, which the unique index is meant to prevent.
            'option1_name' => ['nullable', 'string', 'max:50'],
            'option1_value' => ['nullable', 'string', 'max:100'],
            'option2_name' => ['nullable', 'string', 'max:50'],
            'option2_value' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'option1_name' => trim((string) $this->input('option1_name')),
            'option1_value' => trim((string) $this->input('option1_value')),
            'option2_name' => trim((string) $this->input('option2_name')),
            'option2_value' => trim((string) $this->input('option2_value')),
        ]);
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // Catch the duplicate combination here so the admin sees a
                // field error instead of a 500 from the unique index.
                $productId = $this->route('product')?->id;

                if ($productId === null) {
                    return;
                }

                $exists = ProductVariant::query()
                    ->where('product_id', $productId)
                    ->where('option1_value', $this->input('option1_value'))
                    ->where('option2_value', $this->input('option2_value'))
                    ->when($this->route('variant'), fn ($q, $v) => $q->whereKeyNot($v->id))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add(
                        'option1_value',
                        'This product already has a variation with that combination of options.'
                    );
                }
            },
        ];
    }

    /** Ringgit -> sen. The single conversion point for admin input. */
    public function priceMinor(): int
    {
        return Money::fromDecimalString((string) $this->input('price'));
    }
}
