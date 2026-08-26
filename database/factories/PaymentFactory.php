<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => 'toyyibpay',
            'bill_code' => Str::random(8),
            'provider_ref' => fake()->unique()->numerify('TP##########'),
            'amount_minor' => fake()->numberBetween(2000, 50000),
            'status' => PaymentStatus::Pending,
            'raw_response' => null,
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
