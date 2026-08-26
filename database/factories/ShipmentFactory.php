<?php

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => 'easyparcel',
            'provider_shipment_ref' => null,
            'awb_no' => null,
            'tracking_no' => null,
            'tracking_url' => null,
            'label_url' => null,
            'courier_name' => 'Test Courier',
            'service_id' => 'SVC-TEST',
            'cost_minor' => null,
            'status' => ShipmentStatus::PendingSubmit,
            'raw_response' => null,
            'booked_at' => null,
            'last_tracked_at' => null,
        ];
    }

    public function booked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShipmentStatus::Booked,
            'provider_shipment_ref' => fake()->numerify('EP##########'),
            'awb_no' => fake()->numerify('AWB#########'),
            'tracking_no' => fake()->numerify('TRK#########'),
            'cost_minor' => fake()->numberBetween(500, 2000),
            'booked_at' => now(),
        ]);
    }

    /** The state that exists because a `pay` call timed out — Planning §11.B.5.3. */
    public function needsReconciliation(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShipmentStatus::NeedsReconciliation,
            'provider_shipment_ref' => fake()->numerify('EP##########'),
        ]);
    }
}
