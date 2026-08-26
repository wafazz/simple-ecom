<?php

namespace Database\Factories;

use App\Enums\VariantStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(Str::random(10)),
            'price_minor' => fake()->numberBetween(1000, 20000),
            'stock_qty' => fake()->numberBetween(1, 50),
            'weight_g' => fake()->numberBetween(100, 2000),
            'status' => VariantStatus::Active,
            // '' not null — see Planning §7.1.
            'option1_name' => '',
            'option1_value' => '',
            'option2_name' => '',
            'option2_value' => '',
        ];
    }

    public function options(string $size = '', string $color = ''): static
    {
        return $this->state(fn (array $attributes) => [
            'option1_name' => $size === '' ? '' : 'Size',
            'option1_value' => $size,
            'option2_name' => $color === '' ? '' : 'Color',
            'option2_value' => $color,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => ['stock_qty' => 0]);
    }
}
