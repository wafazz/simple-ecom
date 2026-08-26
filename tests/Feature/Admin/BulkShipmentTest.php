<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\IntegrationToken;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Booking couriers and printing AWBs in bulk — REQ-013.
 *
 * Bulk booking is the single most expensive thing an admin can do: one click,
 * many real charges against the store's courier credit. Most of what is
 * asserted here is therefore what must NOT happen.
 */
class BulkShipmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['must_change_password' => false]);

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

    private function order(OrderStatus $status = OrderStatus::Processing, string $state = 'MY-14'): Order
    {
        $order = Order::factory()->create([
            'order_status' => $status,
            'payment_status' => $status === OrderStatus::Pending
                ? PaymentStatus::Pending
                : PaymentStatus::Paid,
            'state' => $state,
        ]);

        OrderItem::factory()->for($order)->create([
            'product_variant_id' => ProductVariant::factory()->create(['weight_g' => 400])->id,
            'qty' => 1,
        ]);

        return $order->fresh(['items.variant']);
    }

    private function quotationFake(array $serviceIds = ['EP-CS0W', 'EP-CS0X']): array
    {
        return ['data' => [['quotations' => array_map(fn (string $id): array => [
            'courier' => ['service_id' => $id, 'courier_name' => 'Courier '.$id, 'service_name' => 'Standard'],
            'pricing' => ['total_amount' => '10.84', 'currency' => 'MYR'],
        ], $serviceIds)]]];
    }

    private function submitFake(): array
    {
        return ['status_code' => 200, 'message' => '1 request success.', 'data' => [[
            'order_details' => ['order_number' => 'EI-2602-4P2SK'],
            'shipments' => [[
                'status' => 'success',
                'shipment_number' => 'ES-2602-4VW9E',
                'courier' => 'J&T',
                'awb_number' => '7028021894371796',
                'awb_url' => 'https://app.easyparcel.com/label/a4',
                'awb_urls_by_format' => ['A4' => 'https://app.easyparcel.com/label/a4'],
                'tracking_url' => 'https://app.easyparcel.com/track/7028021894371796',
                'pricing_breakdown' => ['currency_code' => 'MYR', 'total_paid_amount' => '10.25'],
            ]],
        ]]];
    }

    #[Test]
    public function the_booking_screen_quotes_but_charges_nothing(): void
    {
        Http::fake(['*/shipment/quotations' => Http::response($this->quotationFake())]);

        $orders = collect([$this->order(), $this->order()]);

        $this->actingAs($this->admin)
            ->get(route('admin.orders.book', ['order_ids' => $orders->pluck('id')->all()]))
            ->assertOk()
            ->assertSee('spends your EasyParcel credit')
            ->assertSee('EP-CS0W');

        $this->assertDatabaseCount('shipments', 0);
        Http::assertNotSent(fn ($r): bool => str_contains($r->url(), 'submit_orders'));
    }

    #[Test]
    public function only_a_service_quoted_for_every_parcel_is_offered(): void
    {
        // Offering one that suits three of five would fail on the other two
        // AFTER the first three had already been charged.
        $a = $this->order(state: 'MY-14');
        $b = $this->order(state: 'MY-12');

        Http::fake(function ($request) {
            $body = $request->data();
            $postcode = data_get($body, 'shipment.0.receiver.postcode');

            return Http::response($this->quotationFake(
                $postcode === 'ZZZZZ' ? ['EP-ONLY'] : ['EP-CS0W', 'EP-SHARED']
            ));
        });

        $this->actingAs($this->admin)
            ->get(route('admin.orders.book', ['order_ids' => [$a->id, $b->id]]))
            ->assertOk()
            ->assertSee('EP-SHARED');
    }

    #[Test]
    public function bulk_booking_charges_once_per_order(): void
    {
        Http::fake([
            '*/shipment/quotations' => Http::response($this->quotationFake()),
            '*/shipment/submit_orders' => Http::response($this->submitFake()),
        ]);

        $orders = collect([$this->order(), $this->order(), $this->order()]);

        $this->actingAs($this->admin)
            ->post(route('admin.orders.book.store'), [
                'service_id' => 'EP-CS0W',
                'order_ids' => $orders->pluck('id')->all(),
            ])
            ->assertRedirect(route('admin.orders.index', ['order_status' => 'processing']))
            ->assertSessionHas('status');

        $this->assertSame(3, Shipment::where('status', ShipmentStatus::Booked->value)->count());

        // Three parcels, three submits. Never more — that count IS the money.
        // Asserted on submit_orders specifically, not on the total request
        // count: the batch also makes one quotation call to resolve the
        // service's display name, and folding that into the same number would
        // make the assertion stop meaning "charges".
        $submits = 0;
        Http::assertSent(function ($request) use (&$submits): bool {
            if (str_contains($request->url(), 'submit_orders')) {
                $submits++;
            }

            return true;
        });

        $this->assertSame(3, $submits);
    }

    #[Test]
    public function the_chosen_service_is_what_reaches_the_courier(): void
    {
        Http::fake(['*/shipment/submit_orders' => Http::response($this->submitFake())]);

        $order = $this->order();

        $this->actingAs($this->admin)->post(route('admin.orders.book.store'), [
            'service_id' => 'EP-PICKED',
            'order_ids' => [$order->id],
        ]);

        Http::assertSent(fn ($r): bool => str_contains($r->url(), 'submit_orders')
            && data_get($r->data(), 'shipment.0.service_id') === 'EP-PICKED');
    }

    #[Test]
    public function an_order_that_already_has_an_awb_is_never_booked_again(): void
    {
        // The request in the admin's words: "if order already assigned AWB,
        // disable for book courier service". Enforced server-side too, because
        // a stale page is not a defence.
        Http::fake(['*/shipment/submit_orders' => Http::response($this->submitFake())]);

        $order = $this->order();
        Shipment::factory()->for($order)->booked()->create(['awb_no' => 'AWB-ALREADY']);

        $this->assertFalse($order->fresh('shipment')->canBookShipment());

        $this->actingAs($this->admin)->post(route('admin.orders.book.store'), [
            'service_id' => 'EP-CS0W',
            'order_ids' => [$order->id],
        ]);

        Http::assertNothingSent();
        $this->assertSame('AWB-ALREADY', $order->fresh('shipment')->shipment->awb_no);
    }

    #[Test]
    public function a_cancelled_or_returned_order_is_never_bookable(): void
    {
        // Posting goods that are not going anywhere.
        foreach ([OrderStatus::Cancelled, OrderStatus::Returned, OrderStatus::NeedsReview] as $status) {
            $this->assertFalse(
                $this->order($status)->canBookShipment(),
                "{$status->value} must not be bookable"
            );
        }

        $this->assertFalse($this->order(OrderStatus::Pending)->canBookShipment());
        $this->assertTrue($this->order(OrderStatus::Processing)->canBookShipment());
        $this->assertTrue($this->order(OrderStatus::NewOrder)->canBookShipment());
    }

    #[Test]
    public function one_failure_does_not_abandon_the_rest(): void
    {
        $good = $this->order();
        $bad = $this->order();

        Http::fake(function ($request) use ($bad) {
            if (! str_contains($request->url(), 'submit_orders')) {
                return Http::response($this->quotationFake());
            }

            $reference = data_get($request->data(), 'shipment.0.reference');

            return $reference === $bad->order_no
                ? Http::response(['message' => 'rejected'], 422)
                : Http::response($this->submitFake());
        });

        $this->actingAs($this->admin)->post(route('admin.orders.book.store'), [
            'service_id' => 'EP-CS0W',
            'order_ids' => [$good->id, $bad->id],
        ])->assertSessionHas('error', fn (string $m): bool => str_contains($m, $good->order_no)
            && str_contains($m, 'failed'));

        $this->assertSame(ShipmentStatus::Booked, $good->fresh('shipment')->shipment->status);
        $this->assertSame(ShipmentStatus::Failed, $bad->fresh('shipment')->shipment->status);
    }

    #[Test]
    public function an_unknown_outcome_outranks_every_other_message(): void
    {
        // The only case where the store may have paid for nothing. It must not
        // be buried under a green "2 booked" notice.
        $good = $this->order();
        $lost = $this->order();

        Http::fake(function ($request) use ($lost) {
            if (! str_contains($request->url(), 'submit_orders')) {
                return Http::response($this->quotationFake());
            }

            return data_get($request->data(), 'shipment.0.reference') === $lost->order_no
                ? Http::response('gateway down', 502)
                : Http::response($this->submitFake());
        });

        $this->actingAs($this->admin)->post(route('admin.orders.book.store'), [
            'service_id' => 'EP-CS0W',
            'order_ids' => [$good->id, $lost->id],
        ])->assertSessionHas('error', fn (string $m): bool => str_contains($m, 'UNKNOWN'))
            ->assertSessionMissing('status');

        $this->assertSame(
            ShipmentStatus::NeedsReconciliation,
            $lost->fresh('shipment')->shipment->status
        );
    }

    #[Test]
    public function the_awb_sheet_lists_only_orders_that_have_one(): void
    {
        $booked = $this->order();
        Shipment::factory()->for($booked)->booked()->create(['awb_no' => 'AWB-123456']);

        $unbooked = $this->order();

        $this->actingAs($this->admin)
            ->get(route('admin.orders.awb', ['order_ids' => [$booked->id, $unbooked->id]]))
            ->assertOk()
            ->assertSee('AWB-123456')
            ->assertDontSee($unbooked->order_no);
    }

    #[Test]
    public function asking_for_awbs_that_do_not_exist_says_so(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin)
            ->get(route('admin.orders.awb', ['order_ids' => [$order->id]]))
            ->assertRedirect(route('admin.orders.index', ['order_status' => 'processing']))
            ->assertSessionHas('error');
    }

    #[Test]
    public function the_bulk_bar_dispatches_without_putting_the_csrf_token_in_a_url(): void
    {
        Http::fake(['*/shipment/quotations' => Http::response($this->quotationFake())]);
        $order = $this->order();

        $response = $this->actingAs($this->admin)->post(route('admin.orders.bulk'), [
            'bulk_action' => 'book',
            'order_ids' => [$order->id],
        ]);

        $response->assertRedirect();
        $this->assertStringNotContainsString('_token', $response->headers->get('Location'));
        $this->assertStringContainsString('order_ids', $response->headers->get('Location'));
    }

    #[Test]
    public function the_courier_and_awb_show_on_every_fulfilment_status(): void
    {
        foreach ([
            OrderStatus::Processing,
            OrderStatus::InDelivery,
            OrderStatus::Completed,
            OrderStatus::Returned,
        ] as $status) {
            $order = $this->order($status);
            Shipment::factory()->for($order)->booked()->create([
                'courier_name' => 'J&T',
                'service_name' => 'J&T — Standard',
                'awb_no' => 'AWB-'.$status->value,
            ]);

            $this->actingAs($this->admin)
                ->get(route('admin.orders.index', ['order_status' => $status->value]))
                ->assertOk()
                ->assertSee('Courier &amp; AWB', false)
                ->assertSee('J&amp;T — Standard', false)
                ->assertSee('AWB-'.$status->value);
        }
    }

    #[Test]
    public function a_fulfilment_page_shows_the_column_even_with_nothing_booked(): void
    {
        // The gap is the useful information here. Hiding the column would
        // read as "no courier data exists" rather than "this is not booked".
        $this->order(OrderStatus::Processing);

        $this->actingAs($this->admin)
            ->get(route('admin.orders.index', ['order_status' => 'processing']))
            ->assertOk()
            ->assertSee('Courier &amp; AWB', false)
            ->assertSee('Not booked');
    }

    #[Test]
    public function a_booked_shipment_with_no_awb_yet_says_so_rather_than_looking_empty(): void
    {
        // Documented behaviour: EasyParcel returns a null AWB at submit time.
        $order = $this->order(OrderStatus::Processing);
        Shipment::factory()->for($order)->create([
            'status' => ShipmentStatus::Booked,
            'courier_name' => 'Aramex',
            'awb_no' => null,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.orders.index', ['order_status' => 'processing']))
            ->assertOk()
            ->assertSee('Aramex')
            ->assertSee('AWB not issued yet');
    }

    #[Test]
    public function the_column_stays_off_a_status_that_has_no_parcels(): void
    {
        $this->order(OrderStatus::NewOrder);

        $this->actingAs($this->admin)
            ->get(route('admin.orders.index', ['order_status' => 'new_order']))
            ->assertOk()
            ->assertDontSee('Courier &amp; AWB', false);
    }

    #[Test]
    public function the_courier_label_never_invents_a_name(): void
    {
        $shipment = Shipment::factory()->create([
            'courier_name' => null, 'service_name' => null, 'service_id' => 'EP-CS0W',
        ]);

        // Ugly but true beats a pleasant fiction.
        $this->assertSame('EP-CS0W', $shipment->courierLabel());

        // And the carrier is not repeated when the label already opens with it.
        $shipment->forceFill(['courier_name' => 'J&T', 'service_name' => 'J&T — Standard'])->save();
        $this->assertSame('J&T — Standard', $shipment->fresh()->courierLabel());

        $shipment->forceFill(['courier_name' => 'J&T', 'service_name' => 'Next Day'])->save();
        $this->assertSame('J&T — Next Day', $shipment->fresh()->courierLabel());
    }

    #[Test]
    public function the_service_name_is_resolved_from_the_quotation_not_the_form(): void
    {
        // A label the admin could type is a label that can disagree with what
        // was actually bought.
        Http::fake([
            '*/shipment/quotations' => Http::response($this->quotationFake(['EP-CS0W'])),
            '*/shipment/submit_orders' => Http::response($this->submitFake()),
        ]);

        $order = $this->order();

        $this->actingAs($this->admin)->post(route('admin.orders.book.store'), [
            'service_id' => 'EP-CS0W',
            'order_ids' => [$order->id],
            'service_name' => 'Totally Made Up Express',
        ]);

        $shipment = $order->fresh('shipment')->shipment;

        $this->assertSame('Courier EP-CS0W — Standard', $shipment->service_name);
        $this->assertNotSame('Totally Made Up Express', $shipment->service_name);
    }

    #[Test]
    public function a_guest_cannot_reach_any_of_it(): void
    {
        $order = $this->order();

        foreach ([
            $this->get(route('admin.orders.book', ['order_ids' => [$order->id]])),
            $this->get(route('admin.orders.awb', ['order_ids' => [$order->id]])),
            $this->post(route('admin.orders.book.store'), ['service_id' => 'X', 'order_ids' => [$order->id]]),
            $this->post(route('admin.orders.bulk'), ['bulk_action' => 'book', 'order_ids' => [$order->id]]),
        ] as $response) {
            $response->assertRedirect(route('admin.login'));
        }

        $this->assertDatabaseCount('shipments', 0);
    }
}
