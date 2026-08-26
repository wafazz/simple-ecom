<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Exceptions\ShipmentBookingFailed;
use App\Exceptions\ShipmentOutcomeUnknown;
use App\Models\IntegrationToken;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentBookingService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-013 — Planning §11.B.5. Booking spends real courier credit. */
class ShipmentBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.easyparcel.base_url' => 'https://api.easyparcel.com/open_api/2026-06',
            'services.easyparcel.oauth_url' => 'https://api.easyparcel.com/oauth',
            'services.easyparcel.client_id' => 'test-client',
            'services.easyparcel.client_secret' => 'test-secret',
        ]);

        foreach ([
            'pickup_name' => 'Kedai Contoh', 'pickup_phone' => '0123456789',
            'pickup_phone_country_code' => 'MY', 'pickup_address_1' => '12 Jalan Perusahaan',
            'pickup_postcode' => '50000', 'pickup_city' => 'Kuala Lumpur',
            'pickup_state' => 'MY-14', 'pickup_country' => 'MY',
            'default_length_mm' => '250', 'default_width_mm' => '180',
            'default_height_mm' => '80', 'collection_lead_days' => '1',
        ] as $key => $value) {
            Setting::put($key, $value);
        }

        IntegrationToken::create([
            'provider' => 'easyparcel',
            'access_token' => 'live-access-token',
            'refresh_token' => 'live-refresh-token',
            'expires_at' => now()->addHours(10),
            'connected_at' => now(),
        ]);
    }

    private function bookableOrder(): Order
    {
        $order = Order::factory()->paid()->create(['courier_service_id' => 'SVC-A']);

        OrderItem::factory()->for($order)->create([
            'product_variant_id' => ProductVariant::factory()->create(['weight_g' => 400])->id,
            'qty' => 1,
        ]);

        return $order->fresh(['items.variant']);
    }

    /** The documented 200 response shape. */
    private function successBody(array $shipmentOverrides = []): array
    {
        return ['status_code' => 200, 'message' => '1 request success, 0 request error.', 'data' => [[
            'order_details' => ['order_number' => 'EI-2602-4P2SK', 'account_id' => 8583757],
            'shipments' => [array_merge([
                'status' => 'success',
                'shipment_number' => 'ES-2602-4VW9E',
                'courier' => 'Aramex',
                'awb_number' => '7028021894371796',
                'awb_url' => 'https://app.easyparcel.com/label/a4',
                'awb_urls_by_format' => ['A4' => 'https://app.easyparcel.com/label/a4', 'A6' => ''],
                'tracking_url' => 'https://app.easyparcel.com/track/7028021894371796',
                'pricing_breakdown' => ['currency_code' => 'MYR', 'total_paid_amount' => '10.25'],
            ], $shipmentOverrides)],
        ]]];
    }

    private function book(Order $order): Shipment
    {
        return app(ShipmentBookingService::class)->book($order);
    }

    #[Test]
    public function a_successful_booking_records_the_awb_and_what_the_courier_charged(): void
    {
        Http::fake(['*/shipment/submit_orders' => Http::response($this->successBody())]);

        $shipment = $this->book($this->bookableOrder());

        $this->assertSame(ShipmentStatus::Booked, $shipment->status);
        $this->assertSame('7028021894371796', $shipment->awb_no);
        $this->assertSame('ES-2602-4VW9E', $shipment->provider_shipment_ref);
        $this->assertSame('Aramex', $shipment->courier_name);
        // "10.25" is a decimal STRING in the response, never minor units.
        $this->assertSame(1025, $shipment->cost_minor);
        $this->assertSame('https://app.easyparcel.com/label/a4', $shipment->label_url);
        $this->assertNotNull($shipment->booked_at);
    }

    #[Test]
    public function an_unpaid_order_is_never_booked(): void
    {
        // Booking spends courier credit against revenue that may never arrive.
        Http::fake();

        $order = Order::factory()->create(['courier_service_id' => 'SVC-A']);
        OrderItem::factory()->for($order)->create();

        $this->expectException(ShipmentBookingFailed::class);

        try {
            $this->book($order->fresh(['items.variant']));
        } finally {
            Http::assertNothingSent();
        }
    }

    #[Test]
    public function incomplete_settings_stop_the_booking_before_any_request(): void
    {
        Http::fake();
        Setting::put('pickup_address_1', '');

        try {
            $this->book($this->bookableOrder());
            $this->fail('Expected the booking to be refused.');
        } catch (ShipmentBookingFailed $e) {
            $this->assertStringContainsString('Store address 1 (Settings)', $e->getMessage());
        }

        // The point of checking up front: no credit was risked.
        Http::assertNothingSent();
    }

    #[Test]
    public function a_second_booking_attempt_never_reaches_the_courier_again(): void
    {
        Http::fake(['*/shipment/submit_orders' => Http::response($this->successBody())]);

        $order = $this->bookableOrder();
        $this->book($order);

        try {
            $this->book($order->fresh(['items.variant', 'shipment']));
            $this->fail('Expected the second booking to be refused.');
        } catch (ShipmentBookingFailed $e) {
            $this->assertStringContainsString('cannot be booked again', $e->getMessage());
        }

        // Exactly one charge, not two.
        Http::assertSentCount(1);
    }

    #[Test]
    public function a_timeout_becomes_needs_reconciliation_and_is_not_retryable(): void
    {
        // The request may have been processed after we stopped listening, so
        // "failed" would be a lie — and failed is retryable.
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        $order = $this->bookableOrder();

        $this->expectException(ShipmentOutcomeUnknown::class);

        try {
            $this->book($order);
        } finally {
            $shipment = $order->fresh('shipment')->shipment;
            $this->assertSame(ShipmentStatus::NeedsReconciliation, $shipment->status);
            $this->assertFalse($shipment->status->isRetryable());
        }
    }

    #[Test]
    public function a_server_error_is_treated_as_an_unknown_outcome_not_a_failure(): void
    {
        Http::fake(['*/shipment/submit_orders' => Http::response('gateway down', 502)]);

        $order = $this->bookableOrder();

        $this->expectException(ShipmentOutcomeUnknown::class);

        try {
            $this->book($order);
        } finally {
            $this->assertSame(
                ShipmentStatus::NeedsReconciliation,
                $order->fresh('shipment')->shipment->status
            );
        }
    }

    #[Test]
    public function a_rejected_parcel_inside_a_200_response_is_a_plain_failure(): void
    {
        // Documented: "1 request success, 1 request error" arrives as HTTP 200.
        Http::fake(['*/shipment/submit_orders' => Http::response($this->successBody([
            'status' => 'error',
            'remarks' => 'Invalid postcode for the selected service.',
        ]))]);

        $order = $this->bookableOrder();

        try {
            $this->book($order);
            $this->fail('Expected the rejected parcel to be reported.');
        } catch (ShipmentBookingFailed $e) {
            $this->assertStringContainsString('Invalid postcode', $e->getMessage());
        }

        // Nothing was charged, so this one IS safe to fix and retry.
        $shipment = $order->fresh('shipment')->shipment;
        $this->assertSame(ShipmentStatus::Failed, $shipment->status);
        $this->assertTrue($shipment->status->isRetryable());
    }

    #[Test]
    public function the_booking_route_is_post_only_and_requires_an_admin(): void
    {
        $order = $this->bookableOrder();

        // A charge must never be reachable by a link, prefetch or crawler.
        $this->get(route('admin.orders.shipment.store', $order))->assertStatus(405);

        $this->post(route('admin.orders.shipment.store', $order))
            ->assertRedirect(route('admin.login'));
    }

    #[Test]
    public function an_admin_books_from_the_order_screen(): void
    {
        Http::fake(['*/shipment/submit_orders' => Http::response($this->successBody())]);

        $order = $this->bookableOrder();

        $this->actingAs(User::factory()->create(['must_change_password' => false]))
            ->post(route('admin.orders.shipment.store', $order))
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('status');

        $this->assertSame(ShipmentStatus::Booked, $order->fresh('shipment')->shipment->status);
    }

    #[Test]
    public function an_unknown_outcome_tells_the_admin_not_to_try_again(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $order = $this->bookableOrder();

        $this->actingAs(User::factory()->create(['must_change_password' => false]))
            ->post(route('admin.orders.shipment.store', $order))
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('error', fn (string $m): bool => str_contains($m, 'UNKNOWN'));
    }

    #[Test]
    public function an_order_cannot_be_booked_twice(): void
    {
        // The structural guard: a second "Book shipment" click must hit a
        // duplicate-key error rather than a second real-money charge.
        $order = Order::factory()->create();

        Shipment::factory()->for($order)->create();

        $this->expectException(QueryException::class);

        Shipment::factory()->for($order)->create();
    }

    #[Test]
    public function only_one_caller_can_move_a_shipment_out_of_pending_submit(): void
    {
        $shipment = Shipment::factory()->create();

        $first = Shipment::transitionAtomically(
            $shipment->id, ShipmentStatus::PendingSubmit, ShipmentStatus::Submitting
        );
        $second = Shipment::transitionAtomically(
            $shipment->id, ShipmentStatus::PendingSubmit, ShipmentStatus::Submitting
        );

        $this->assertTrue($first);
        $this->assertFalse($second);
        $this->assertSame(ShipmentStatus::Submitting, $shipment->fresh()->status);
    }

    #[Test]
    public function a_shipment_starts_in_pending_submit_before_any_api_call(): void
    {
        // The row must exist before EasyParcel is contacted, so a failed DB
        // write can never leave a paid booking unrecorded.
        $this->assertSame(
            ShipmentStatus::PendingSubmit,
            Shipment::factory()->create()->status
        );
    }

    #[Test]
    public function an_ambiguous_payment_outcome_is_never_retryable(): void
    {
        // needs_reconciliation means "we do not know whether money left the
        // wallet". Auto-retry here is how a store pays twice.
        $this->assertFalse(ShipmentStatus::NeedsReconciliation->isRetryable());
        $this->assertTrue(ShipmentStatus::NeedsReconciliation->needsAttention());

        $this->assertTrue(ShipmentStatus::Failed->isRetryable());
        $this->assertTrue(ShipmentStatus::PendingSubmit->isRetryable());

        // A booked shipment must never be re-bookable either.
        $this->assertFalse(ShipmentStatus::Booked->isRetryable());
        $this->assertFalse(ShipmentStatus::Paid->isRetryable());
    }

    #[Test]
    public function the_reconciliation_scope_returns_exactly_the_rows_needing_a_human(): void
    {
        Shipment::factory()->booked()->create();
        Shipment::factory()->needsReconciliation()->create();
        Shipment::factory()->create(['status' => ShipmentStatus::Failed]);

        $this->assertSame(2, Shipment::needsAttention()->count());
    }

    #[Test]
    public function a_booked_shipment_records_what_the_courier_actually_charged(): void
    {
        $shipment = Shipment::factory()->booked()->create(['cost_minor' => 1250]);

        $this->assertIsInt($shipment->fresh()->cost_minor);
        $this->assertNotNull($shipment->awb_no);
        $this->assertNotNull($shipment->booked_at);
    }
}
