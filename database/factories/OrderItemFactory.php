<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $unit = fake()->numberBetween(1000, 20000);
        $qty = fake()->numberBetween(1, 3);

        return [
            'order_id' => Order::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'product_name' => fake()->words(3, true),
            'variation_label' => 'M / Black',
            'sku' => strtoupper(fake()->bothify('???-####')),
            'unit_price_minor' => $unit,
            'qty' => $qty,
            'line_total_minor' => $unit * $qty,
        ];
    }
}
