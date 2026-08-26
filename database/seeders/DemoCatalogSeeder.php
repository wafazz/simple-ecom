<?php

namespace Database\Seeders;

use App\Enums\VariantStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo catalogue. Reproduces the spec §9 example table exactly, plus a
 * single-variant product to prove the "every product has at least one variant"
 * rule works for goods with no options at all (Planning §7).
 */
class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $apparel = Category::updateOrCreate(
            ['slug' => 'apparel'],
            ['name' => 'Apparel', 'is_active' => true]
        );

        $accessories = Category::updateOrCreate(
            ['slug' => 'accessories'],
            ['name' => 'Accessories', 'is_active' => true]
        );

        $tshirt = Product::updateOrCreate(
            ['slug' => 't-shirt'],
            [
                'category_id' => $apparel->id,
                'name' => 'T-Shirt',
                'description' => 'Cotton t-shirt available in several sizes and colours.',
                'is_active' => true,
            ]
        );

        // Straight from spec §9.
        $combinations = [
            ['S', 'Black', 3000, 10],
            ['M', 'Black', 3000, 20],
            ['L', 'Black', 3200, 15],
            ['S', 'White', 3000, 8],
        ];

        foreach ($combinations as [$size, $color, $priceMinor, $stock]) {
            ProductVariant::updateOrCreate(
                [
                    'product_id' => $tshirt->id,
                    'option1_value' => $size,
                    'option2_value' => $color,
                ],
                [
                    'sku' => 'TS-'.strtoupper($size).'-'.strtoupper(Str::substr($color, 0, 3)),
                    'price_minor' => $priceMinor,
                    'stock_qty' => $stock,
                    'weight_g' => 200,
                    'status' => VariantStatus::Active,
                    'option1_name' => 'Size',
                    'option2_name' => 'Color',
                ]
            );
        }

        // No options at all — still gets a variant, with '' in both option
        // slots. This is the row that would break the unique index if the
        // columns were nullable (Planning §7.1).
        $tote = Product::updateOrCreate(
            ['slug' => 'canvas-tote-bag'],
            [
                'category_id' => $accessories->id,
                'name' => 'Canvas Tote Bag',
                'description' => 'Plain canvas tote. One size.',
                'is_active' => true,
            ]
        );

        ProductVariant::updateOrCreate(
            ['product_id' => $tote->id, 'option1_value' => '', 'option2_value' => ''],
            [
                'sku' => 'TOTE-STD',
                'price_minor' => 2500,
                'stock_qty' => 30,
                'weight_g' => 300,
                'status' => VariantStatus::Active,
                'option1_name' => '',
                'option2_name' => '',
            ]
        );
    }
}
