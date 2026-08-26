<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(2000, 50000);
        $shipping = fake()->numberBetween(500, 2000);

        return [
            'order_no' => 'ORD-'.now()->format('Ymd').'-'.fake()->unique()->numberBetween(1000, 9999),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => '01'.fake()->numerify('#########'),
            'address_line' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => 'MY-14',
            'postcode' => fake()->numerify('#####'),
            'country' => 'MY',
            'subtotal_minor' => $subtotal,
            'shipping_fee_minor' => $shipping,
            'grand_total_minor' => $subtotal + $shipping,
            'courier_name' => 'Test Courier',
            'courier_service_id' => 'SVC-TEST',
            'shipping_rate_source' => 'api',
            'order_status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => PaymentStatus::Paid,
            'order_status' => OrderStatus::Processing,
        ]);
    }

    public function flatRate(): static
    {
        return $this->state(fn (array $attributes) => ['shipping_rate_source' => 'flat']);
    }
}
