<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Support\ShipmentPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * REQ-013 — the request body EasyParcel receives.
 *
 * Verified against the official `shipment/submit_orders` reference on
 * 2026-08-27. The units are the part worth guarding: the store keeps grams and
 * millimetres, the API wants kilograms and centimetres, and a factor-of-1000
 * slip is a parcel quoted at the wrong price with real money behind it.
 */
class ShipmentPayloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'pickup_name' => 'Kedai Contoh',
            'pickup_phone' => '012-345 6789',
            'pickup_phone_country_code' => 'MY',
            'pickup_email' => 'store@example.test',
            'pickup_address_1' => '12 Jalan Perusahaan',
            'pickup_postcode' => '50000',
            'pickup_city' => 'Kuala Lumpur',
            'pickup_state' => 'MY-14',
            'pickup_country' => 'MY',
            'default_length_mm' => '250',
            'default_width_mm' => '180',
            'default_height_mm' => '80',
            'collection_lead_days' => '1',
        ] as $key => $value) {
            Setting::put($key, $value);
        }
    }

    private function bookableOrder(array $variantOverrides = []): Order
    {
        $order = Order::factory()->paid()->create(['courier_service_id' => 'SVC-A']);

        $variant = ProductVariant::factory()->create(array_merge([
            'weight_g' => 400, 'length_mm' => 300, 'width_mm' => 200, 'height_mm' => 100,
        ], $variantOverrides));

        OrderItem::factory()->for($order)->create([
            'product_variant_id' => $variant->id,
            'product_name' => 'Batik Shirt',
            'variation_label' => 'M / Black',
            'unit_price_minor' => 4550,
            'qty' => 2,
        ]);

        return $order->fresh(['items.variant']);
    }

    #[Test]
    public function grams_and_millimetres_become_kilograms_and_centimetres(): void
    {
        // The single most expensive mistake available here.
        $parcel = ShipmentPayload::for($this->bookableOrder(), 'EP-CS0W')['shipment'][0];

        $this->assertSame(0.8, $parcel['weight']);   // 400 g × 2
        $this->assertSame(30.0, $parcel['length']);  // 300 mm
        $this->assertSame(20.0, $parcel['width']);
        $this->assertSame(10.0, $parcel['height']);
    }

    #[Test]
    public function a_variant_without_its_own_size_falls_back_to_the_store_default(): void
    {
        // Nothing may be submitted at zero size — the courier rejects it.
        $parcel = ShipmentPayload::for($this->bookableOrder([
            'length_mm' => 0, 'width_mm' => 0, 'height_mm' => 0,
        ]), 'EP-CS0W')['shipment'][0];

        $this->assertSame(25.0, $parcel['length']);
        $this->assertSame(18.0, $parcel['width']);
        $this->assertSame(8.0, $parcel['height']);
    }

    #[Test]
    public function every_field_the_api_marks_required_is_present(): void
    {
        $parcel = ShipmentPayload::for($this->bookableOrder(), 'EP-CS0W')['shipment'][0];

        foreach (['service_id', 'collection_date', 'weight', 'height', 'length',
            'width', 'item', 'sender', 'receiver', 'feature'] as $key) {
            $this->assertArrayHasKey($key, $parcel, "missing required shipment key [{$key}]");
        }

        foreach (['name', 'phone_number_country_code', 'phone_number',
            'address_1', 'postcode', 'city', 'country_code'] as $key) {
            $this->assertArrayHasKey($key, $parcel['sender'], "missing required sender key [{$key}]");
            $this->assertArrayHasKey($key, $parcel['receiver'], "missing required receiver key [{$key}]");
        }

        foreach (['content', 'weight', 'height', 'length', 'width',
            'currency_code', 'value', 'quantity'] as $key) {
            $this->assertArrayHasKey($key, $parcel['item'][0], "missing required item key [{$key}]");
        }
    }

    #[Test]
    public function the_collection_date_is_a_plain_date_in_the_future(): void
    {
        Setting::put('collection_lead_days', '2');

        $this->assertSame(
            now()->addDays(2)->format('Y-m-d'),
            ShipmentPayload::for($this->bookableOrder(), 'EP-CS0W')['shipment'][0]['collection_date']
        );
    }

    #[Test]
    public function the_declared_item_value_is_what_the_customer_actually_paid(): void
    {
        // Sen in the database, a decimal for the customs declaration.
        $item = ShipmentPayload::for($this->bookableOrder(), 'EP-CS0W')['shipment'][0]['item'][0];

        $this->assertSame(45.5, $item['value']);
        $this->assertSame(2, $item['quantity']);
        $this->assertSame('Batik Shirt M / Black', $item['content']);
    }

    #[Test]
    public function phone_numbers_are_sent_as_digits_without_the_trunk_zero(): void
    {
        $parcel = ShipmentPayload::for($this->bookableOrder(), 'EP-CS0W')['shipment'][0];

        // "012-345 6789" — the country code carries the prefix instead.
        $this->assertSame('123456789', $parcel['sender']['phone_number']);
        $this->assertSame('MY', $parcel['sender']['phone_number_country_code']);
    }

    #[Test]
    public function missing_sender_details_are_reported_before_any_request(): void
    {
        Setting::put('pickup_address_1', '');
        Setting::put('pickup_city', '');

        $missing = ShipmentPayload::missingFor($this->bookableOrder());

        $this->assertFalse(ShipmentPayload::isReady($this->bookableOrder()));
        $this->assertContains('Store address 1 (Settings)', $missing);
        $this->assertContains('Store city (Settings)', $missing);
    }

    #[Test]
    public function the_courier_service_comes_from_the_admin_never_from_the_order(): void
    {
        // orders.courier_service_id holds what the CUSTOMER was quoted. Since
        // delivery moved to the store's own weight table (REQ-006) that is
        // 'weight-west' — not an EasyParcel product, and it would be rejected
        // outright. The admin picks the real service when they book.
        $order = $this->bookableOrder();
        $order->forceFill(['courier_service_id' => 'weight-west'])->save();
        $order = $order->fresh(['items.variant']);

        // Not a blocker: readiness is about ADDRESS and PARCEL data.
        $this->assertSame([], ShipmentPayload::missingFor($order));

        $parcel = ShipmentPayload::for($order, 'EP-CS0W')['shipment'][0];

        $this->assertSame('EP-CS0W', $parcel['service_id']);
        $this->assertStringNotContainsString('weight-', json_encode($parcel));
    }

    #[Test]
    public function a_complete_order_reports_nothing_missing(): void
    {
        $this->assertSame([], ShipmentPayload::missingFor($this->bookableOrder()));
    }
}
