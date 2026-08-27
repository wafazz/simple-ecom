<?php

namespace Tests\Feature\Admin;

use App\Enums\Courier;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Manual fulfilment — the admin books with the courier themselves and records
 * the AWB, while the EasyParcel integration is on hold.
 *
 * The two rules worth the most here are that a PAID EasyParcel booking can
 * never be overwritten by hand, and that an uploaded label — which carries the
 * customer's name, address and phone — is never readable without logging in.
 */
class ManualShipmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        Storage::fake('awb');
    }

    private function order(
        OrderStatus $status = OrderStatus::Processing,
        PaymentStatus $payment = PaymentStatus::Paid,
    ): Order {
        return Order::factory()->create([
            'order_status' => $status,
            'payment_status' => $payment,
        ]);
    }

    private function save(Order $order, array $overrides = [])
    {
        return $this->actingAs($this->admin)->post(
            route('admin.orders.awb.store', $order),
            array_merge([
                'courier' => Courier::JTExpress->value,
                'awb_no' => 'JT0123456789',
            ], $overrides),
        );
    }

    // ------------------------------------------------------------ recording

    #[Test]
    public function an_admin_records_a_courier_and_awb_by_hand(): void
    {
        $order = $this->order();

        $this->save($order)
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('status');

        $shipment = $order->fresh()->shipment;

        $this->assertSame('manual', $shipment->provider);
        $this->assertSame('J&T Express', $shipment->courier_name);
        $this->assertSame('JT0123456789', $shipment->awb_no);
        $this->assertSame(ShipmentStatus::Booked, $shipment->status);
        $this->assertNotNull($shipment->booked_at);
    }

    #[Test]
    public function the_order_status_is_left_alone(): void
    {
        $order = $this->order(OrderStatus::Processing);

        $this->save($order);

        // Recording an AWB says the parcel exists, not that it has been
        // collected. The admin moves the order on deliberately.
        $this->assertSame(OrderStatus::Processing, $order->fresh()->order_status);
    }

    #[Test]
    public function the_uploaded_label_is_stored_on_the_private_disk(): void
    {
        $order = $this->order();

        $this->save($order, ['awb_file' => UploadedFile::fake()->create('awb.pdf', 12, 'application/pdf')]);

        $path = $order->fresh()->shipment->label_path;

        $this->assertNotNull($path);
        Storage::disk('awb')->assertExists($path);
    }

    #[Test]
    public function the_awb_number_alone_is_enough(): void
    {
        $order = $this->order();

        $this->save($order)->assertSessionHasNoErrors();

        $this->assertNull($order->fresh()->shipment->label_path);
    }

    // ------------------------------------------------------------ the label

    #[Test]
    public function an_admin_can_open_a_stored_label(): void
    {
        $order = $this->order();
        $this->save($order, ['awb_file' => UploadedFile::fake()->create('awb.pdf', 12, 'application/pdf')]);

        $this->actingAs($this->admin)
            ->get(route('admin.orders.awb.label', $order))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'inline');
    }

    #[Test]
    public function a_guest_cannot_open_a_stored_label(): void
    {
        // Built directly rather than through save(): actingAs() persists for
        // the rest of a test, so posting the label as the admin first would
        // leave this request authenticated and the assertion would pass
        // without proving anything.
        $order = $this->order();
        Storage::disk('awb')->put('labels/private.pdf', 'customer name, address, phone');

        Shipment::factory()->create([
            'order_id' => $order->id,
            'provider' => Shipment::PROVIDER_MANUAL,
            'label_path' => 'labels/private.pdf',
        ]);

        $this->assertGuest();

        // An airway bill is the customer's name, address and phone on one page.
        $this->get(route('admin.orders.awb.label', $order))
            ->assertRedirect(route('admin.login'));
    }

    #[Test]
    public function asking_for_a_label_that_does_not_exist_is_a_404(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.orders.awb.label', $this->order()))
            ->assertNotFound();
    }

    // ------------------------------------------------------------ replacing

    #[Test]
    public function correcting_the_number_keeps_the_stored_label(): void
    {
        $order = $this->order();
        $this->save($order, ['awb_file' => UploadedFile::fake()->create('awb.pdf', 12, 'application/pdf')]);

        $original = $order->fresh()->shipment->label_path;

        $this->save($order, ['awb_no' => 'JT9999999999']);

        $shipment = $order->fresh()->shipment;

        $this->assertSame('JT9999999999', $shipment->awb_no);
        $this->assertSame($original, $shipment->label_path, 'Fixing a typo must not wipe the label.');
        Storage::disk('awb')->assertExists($original);
    }

    #[Test]
    public function uploading_a_new_label_replaces_and_deletes_the_old_one(): void
    {
        $order = $this->order();
        $this->save($order, ['awb_file' => UploadedFile::fake()->create('first.pdf', 12, 'application/pdf')]);
        $first = $order->fresh()->shipment->label_path;

        $this->save($order, ['awb_file' => UploadedFile::fake()->create('second.pdf', 12, 'application/pdf')]);
        $second = $order->fresh()->shipment->label_path;

        $this->assertNotSame($first, $second);
        Storage::disk('awb')->assertExists($second);
        Storage::disk('awb')->assertMissing($first);
    }

    #[Test]
    public function recording_twice_never_creates_a_second_shipment(): void
    {
        $order = $this->order();

        $this->save($order);
        $this->save($order, ['awb_no' => 'JT2222222222']);

        $this->assertSame(1, Shipment::where('order_id', $order->id)->count());
    }

    // ------------------------------------------------------------ the guards

    #[Test]
    public function an_unpaid_order_cannot_be_fulfilled_by_hand(): void
    {
        $order = $this->order(OrderStatus::Pending, PaymentStatus::Pending);

        $this->save($order)->assertForbidden();

        $this->assertNull($order->fresh()->shipment);
    }

    #[Test]
    public function a_paid_easyparcel_booking_cannot_be_overwritten_by_hand(): void
    {
        $order = $this->order();

        // Real money already left the store's courier credit for this parcel.
        Shipment::factory()->create([
            'order_id' => $order->id,
            'provider' => 'easyparcel',
            'status' => ShipmentStatus::Booked,
            'awb_no' => 'EP-REAL-0001',
            'courier_name' => 'Skynet',
        ]);

        $this->save($order)->assertForbidden();

        $shipment = $order->fresh()->shipment;
        $this->assertSame('EP-REAL-0001', $shipment->awb_no);
        $this->assertSame('Skynet', $shipment->courier_name);
    }

    #[Test]
    public function an_ambiguous_easyparcel_outcome_cannot_be_overwritten_by_hand(): void
    {
        $order = $this->order();

        // needs_reconciliation means we do not know whether the store paid.
        // Typing over it would destroy the only record of the question.
        Shipment::factory()->create([
            'order_id' => $order->id,
            'provider' => 'easyparcel',
            'status' => ShipmentStatus::NeedsReconciliation,
        ]);

        $this->save($order)->assertForbidden();
    }

    #[Test]
    public function a_failed_easyparcel_attempt_may_be_replaced_by_hand(): void
    {
        $order = $this->order();

        // Nothing was charged, so there is nothing to protect.
        Shipment::factory()->create([
            'order_id' => $order->id,
            'provider' => 'easyparcel',
            'status' => ShipmentStatus::Failed,
            'awb_no' => null,
        ]);

        $this->save($order)->assertSessionHasNoErrors();

        $this->assertSame('manual', $order->fresh()->shipment->provider);
    }

    #[Test]
    public function taking_over_a_failed_attempt_clears_its_easyparcel_fields(): void
    {
        $order = $this->order();

        Shipment::factory()->create([
            'order_id' => $order->id,
            'provider' => 'easyparcel',
            'status' => ShipmentStatus::Failed,
            'awb_no' => null,
            'label_url' => 'https://app.easyparcel.com/label/never-shipped',
            'tracking_url' => 'https://track.easyparcel.com/never-shipped',
            'service_id' => 'EP-CS0W',
            'cost_minor' => 1234,
        ]);

        $this->save($order);

        $shipment = $order->fresh()->shipment;

        // Otherwise labelUrl() hands the admin EasyParcel's label for a parcel
        // EasyParcel never shipped.
        $this->assertNull($shipment->label_url);
        $this->assertNull($shipment->labelUrl());
        $this->assertNull($shipment->service_id);
        $this->assertNull($shipment->cost_minor);

        // Tracking is not cleared but REPLACED: it now points at the courier
        // actually carrying the parcel, not the one that was never booked.
        $this->assertStringNotContainsString('easyparcel', $shipment->tracking_url);
        $this->assertSame(
            'https://jtexpress.my/tracking/JT0123456789',
            $shipment->tracking_url,
        );
    }

    #[Test]
    public function a_guest_cannot_record_a_shipment(): void
    {
        $order = $this->order();

        $this->post(route('admin.orders.awb.store', $order), [
            'courier' => Courier::PosLaju->value,
            'awb_no' => 'PL123456789MY',
        ])->assertRedirect(route('admin.login'));

        $this->assertNull($order->fresh()->shipment);
    }

    // --------------------------------------------------------- on the screen

    /**
     * An order the Book courier branch will actually render for.
     *
     * ShipmentPayload::missingFor() must come back empty, or the view shows
     * "Not ready to book" instead and every assertion below would be testing
     * the wrong branch.
     */
    private function shipReadyOrder(): Order
    {
        foreach ([
            'pickup_name' => 'Kedai Contoh', 'pickup_phone' => '0123456789',
            'pickup_address_1' => '12 Jalan Perusahaan', 'pickup_postcode' => '50000',
            'pickup_city' => 'Kuala Lumpur', 'pickup_state' => 'MY-14',
            'pickup_country' => 'MY',
        ] as $key => $value) {
            Setting::put($key, $value);
        }

        $order = $this->order();

        OrderItem::factory()->for($order)->create([
            'product_variant_id' => ProductVariant::factory()->create(['weight_g' => 400])->id,
            'qty' => 1,
        ]);

        return $order->fresh(['items.variant']);
    }

    private function showPage(Order $order)
    {
        return $this->actingAs($this->admin)->get(route('admin.orders.show', $order));
    }

    #[Test]
    public function the_manual_form_is_offered_on_a_paid_order(): void
    {
        $this->showPage($this->order())
            ->assertOk()
            ->assertSee('Enter AWB manually')
            ->assertSee('NinjaVan')
            ->assertSee('PosLaju')
            ->assertSee('J&amp;T Express', false)
            ->assertSee('SPX Express');
    }

    #[Test]
    public function booking_is_disabled_while_easyparcel_is_not_connected(): void
    {
        $order = $this->shipReadyOrder();

        $content = $this->showPage($order)->assertOk()
            ->assertSee('EasyParcel is not connected')
            ->getContent();

        // Present but unusable, and the reason is on the page — a control that
        // vanishes reads as a broken screen.
        $this->assertMatchesRegularExpression(
            '/<button[^>]*\sdisabled[^>]*>\s*<i class="bi bi-truck[^>]*><\/i>Book courier/',
            $content,
            'Book courier must be a disabled <button>: an <a> ignores the disabled attribute.',
        );

        // And no live route to the money-spending screen anywhere on the page.
        $this->assertStringNotContainsString(
            'orders/book',
            $content,
        );
    }

    #[Test]
    public function booking_is_offered_again_once_easyparcel_is_connected(): void
    {
        $order = $this->shipReadyOrder();

        $this->connectEasyParcel();

        $content = $this->showPage($order)->assertOk()
            ->assertDontSee('EasyParcel is not connected')
            ->getContent();

        $this->assertStringContainsString('orders/book', $content);
        $this->assertDoesNotMatchRegularExpression(
            '/<button[^>]*\sdisabled[^>]*>\s*<i class="bi bi-truck/',
            $content,
        );
    }

    private function connectEasyParcel(): void
    {
        config([
            'services.easyparcel.client_id' => 'test-client',
            'services.easyparcel.client_secret' => 'test-secret',
        ]);

        IntegrationToken::query()->create([
            'provider' => 'easyparcel',
            'access_token' => 'live-access-token',
            'refresh_token' => 'live-refresh-token',
            'expires_at' => now()->addHours(10),
        ]);
    }

    #[Test]
    public function the_orders_list_withdraws_booking_and_says_why(): void
    {
        $this->order(OrderStatus::NewOrder);

        $content = $this->actingAs($this->admin)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('EasyParcel is not connected')
            ->getContent();

        // A list of dead buttons is worse than one explanation, so the control
        // goes rather than being disabled per row.
        $this->assertStringNotContainsString('orders/book', $content);
    }

    #[Test]
    public function the_orders_list_offers_booking_once_connected(): void
    {
        $this->order(OrderStatus::NewOrder);
        $this->connectEasyParcel();

        $this->actingAs($this->admin)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertDontSee('EasyParcel is not connected')
            ->assertSee('orders/book', false);
    }

    #[Test]
    public function the_notice_stays_away_when_nothing_could_be_booked_anyway(): void
    {
        // Unpaid, so booking was never on offer — the notice would be noise.
        $this->order(OrderStatus::Pending, PaymentStatus::Pending);

        $this->actingAs($this->admin)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertDontSee('EasyParcel is not connected');
    }

    #[Test]
    public function a_stored_label_is_linked_from_the_order(): void
    {
        $order = $this->order();
        $this->save($order, ['awb_file' => UploadedFile::fake()->create('awb.pdf', 12, 'application/pdf')]);

        $this->showPage($order)
            ->assertOk()
            ->assertSee(route('admin.orders.awb.label', $order), false);
    }

    // ------------------------------------------------------------- tracking

    public static function courierTrackingUrls(): array
    {
        return [
            'NinjaVan' => [Courier::NinjaVan, 'https://www.ninjavan.co/en-my/tracking?id=NV123456789'],
            'PosLaju' => [Courier::PosLaju, 'https://tracking.pos.com.my/tracking/NV123456789'],
            'J&T Express' => [Courier::JTExpress, 'https://jtexpress.my/tracking/NV123456789'],
            'SPX Express' => [Courier::SPXExpress, 'https://spx.com.my/track?NV123456789'],
        ];
    }

    #[Test]
    #[DataProvider('courierTrackingUrls')]
    public function each_courier_gets_its_own_tracking_link(Courier $courier, string $expected): void
    {
        $order = $this->order();

        $this->save($order, ['courier' => $courier->value, 'awb_no' => 'NV123456789']);

        $shipment = $order->fresh()->shipment;

        $this->assertSame($expected, $shipment->tracking_url);
        $this->assertSame('NV123456789', $shipment->tracking_no);
    }

    #[Test]
    public function a_slash_in_an_awb_cannot_invent_a_path_segment(): void
    {
        $order = $this->order();

        // The validation rule permits a slash, and three of the four carriers
        // put the AWB in the path — unencoded, this would 404 quietly.
        $this->save($order, ['courier' => Courier::PosLaju->value, 'awb_no' => 'AB/12'])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'https://tracking.pos.com.my/tracking/AB%2F12',
            $order->fresh()->shipment->tracking_url,
        );
    }

    #[Test]
    public function correcting_the_awb_moves_the_tracking_link_with_it(): void
    {
        $order = $this->order();

        $this->save($order, ['courier' => Courier::NinjaVan->value, 'awb_no' => 'NVOLD1']);
        $this->save($order, ['courier' => Courier::NinjaVan->value, 'awb_no' => 'NVNEW2']);

        $this->assertSame(
            'https://www.ninjavan.co/en-my/tracking?id=NVNEW2',
            $order->fresh()->shipment->tracking_url,
        );
    }

    #[Test]
    public function switching_courier_rewrites_the_tracking_link(): void
    {
        $order = $this->order();

        $this->save($order, ['courier' => Courier::NinjaVan->value, 'awb_no' => 'X1']);
        $this->save($order, ['courier' => Courier::SPXExpress->value, 'awb_no' => 'X1']);

        $shipment = $order->fresh()->shipment;

        $this->assertSame('SPX Express', $shipment->courier_name);
        $this->assertSame('https://spx.com.my/track?X1', $shipment->tracking_url);
    }

    #[Test]
    public function the_tracking_link_is_offered_on_the_order(): void
    {
        $order = $this->order();
        $this->save($order, ['courier' => Courier::PosLaju->value, 'awb_no' => 'PL999']);

        $this->showPage($order)
            ->assertOk()
            ->assertSee('Track parcel')
            ->assertSee('https://tracking.pos.com.my/tracking/PL999', false);
    }

    // ------------------------------------------------------------ validation

    #[Test]
    public function the_courier_must_be_one_of_the_four_offered(): void
    {
        $this->save($this->order(), ['courier' => 'dhl'])
            ->assertSessionHasErrors('courier');
    }

    #[Test]
    public function the_awb_number_is_required(): void
    {
        $this->save($this->order(), ['awb_no' => ''])
            ->assertSessionHasErrors('awb_no');
    }

    #[Test]
    public function a_pasted_tracking_url_is_rejected_as_an_awb_number(): void
    {
        $this->save($this->order(), ['awb_no' => 'https://track.example/x?id=1'])
            ->assertSessionHasErrors('awb_no');
    }

    #[Test]
    public function an_executable_upload_is_rejected(): void
    {
        $order = $this->order();

        $this->save($order, ['awb_file' => UploadedFile::fake()->create('shell.php', 8, 'application/x-php')])
            ->assertSessionHasErrors('awb_file');

        $this->assertNull($order->fresh()->shipment);
    }

    #[Test]
    public function an_oversized_label_is_rejected(): void
    {
        $this->save($this->order(), [
            'awb_file' => UploadedFile::fake()->create('huge.pdf', 9000, 'application/pdf'),
        ])->assertSessionHasErrors('awb_file');
    }
}
