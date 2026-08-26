<?php

namespace App\Http\Requests;

use App\Enums\VariantStatus;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * REQ-001 / REQ-002 — one form defines a product AND its variations.
 *
 * A "simple" product is not a special case in the schema: it is a product with
 * exactly one variant whose option slots are empty strings (Planning §7.1). The
 * type toggle is an admin convenience, not a data model.
 */
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
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_active' => ['boolean'],

            'product_type' => ['required', Rule::in(['simple', 'variable'])],

            // Every product has at least one variant — price and stock live
            // only there, so a product without one cannot be sold.
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.sku' => ['required', 'string', 'max:64'],
            'variants.*.price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'variants.*.stock_qty' => ['required', 'integer', 'min:0'],
            'variants.*.weight_g' => ['required', 'integer', 'min:0', 'max:100000'],
            // Blank falls back to the store default; booking never sends zero.
            'variants.*.length_mm' => ['nullable', 'integer', 'min:0', 'max:300000'],
            'variants.*.width_mm' => ['nullable', 'integer', 'min:0', 'max:300000'],
            'variants.*.height_mm' => ['nullable', 'integer', 'min:0', 'max:300000'],
            'variants.*.status' => ['required', Rule::enum(VariantStatus::class)],
            'variants.*.option1_name' => ['nullable', 'string', 'max:50'],
            'variants.*.option1_value' => ['nullable', 'string', 'max:100'],
            'variants.*.option2_name' => ['nullable', 'string', 'max:50'],
            'variants.*.option2_value' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => filled($this->input('slug'))
                ? str($this->input('slug'))->slug()->value()
                : str((string) $this->input('name'))->slug()->value(),
            'is_active' => $this->boolean('is_active'),
            'product_type' => $this->input('product_type', 'simple'),
            // Rows are submitted keyed by index; JS removal leaves gaps.
            'variants' => array_values((array) $this->input('variants', [])),
        ]);
    }

    public function after(): array
    {
        return [
            fn (Validator $v) => $this->assertSkusAreUnique($v),
            fn (Validator $v) => $this->assertCombinationsAreUnique($v),
            fn (Validator $v) => $this->assertSimpleHasOneRow($v),
        ];
    }

    /**
     * SKU is globally unique. Checked against the database (ignoring each row's
     * own variant) AND within the submitted set, because two new rows sharing a
     * SKU would otherwise pass validation and die on the unique index.
     */
    private function assertSkusAreUnique(Validator $validator): void
    {
        $seen = [];

        foreach ($this->variantRows() as $i => $row) {
            $sku = trim((string) ($row['sku'] ?? ''));

            if ($sku === '') {
                continue;
            }

            if (isset($seen[$sku])) {
                $validator->errors()->add("variants.{$i}.sku", "SKU {$sku} is used twice in this form.");

                continue;
            }
            $seen[$sku] = true;

            $taken = ProductVariant::query()
                ->where('sku', $sku)
                ->when($row['id'] ?? null, fn ($q, $id) => $q->whereKeyNot($id))
                ->exists();

            if ($taken) {
                $validator->errors()->add("variants.{$i}.sku", "SKU {$sku} already belongs to another product.");
            }
        }
    }

    /**
     * A product cannot hold two variants with the same option combination —
     * enforced by a unique index, surfaced here as a field error rather than a
     * 500 (Planning §7.1).
     */
    private function assertCombinationsAreUnique(Validator $validator): void
    {
        $seen = [];

        foreach ($this->variantRows() as $i => $row) {
            $key = trim((string) ($row['option1_value'] ?? '')).'|'.trim((string) ($row['option2_value'] ?? ''));

            if (isset($seen[$key])) {
                $label = trim(str_replace('|', ' / ', $key), ' /');
                $validator->errors()->add(
                    "variants.{$i}.option1_value",
                    $label === ''
                        ? 'Only one variation is allowed when there are no options.'
                        : "The combination {$label} appears twice."
                );

                continue;
            }

            $seen[$key] = true;
        }
    }

    /** A simple product is one variant with no options. */
    private function assertSimpleHasOneRow(Validator $validator): void
    {
        if ($this->input('product_type') !== 'simple') {
            return;
        }

        if (count($this->variantRows()) !== 1) {
            $validator->errors()->add('variants', 'A simple product has exactly one variation.');
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function variantRows(): array
    {
        return array_values((array) $this->input('variants', []));
    }

    /** Ringgit -> sen, converted once. */
    public function priceMinorFor(array $row): int
    {
        return Money::fromDecimalString((string) ($row['price'] ?? '0'));
    }
}
