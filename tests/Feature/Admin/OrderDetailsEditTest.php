<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Correcting the delivery and contact details of a New Order.
 *
 * The rule underneath every test here is that this screen moves no money and
 * no stock. A New Order has been settled, so the amount collected is already
 * fixed; an edit that could change what the order is worth would leave the
 * record disagreeing with what the customer actually paid.
 */
class OrderDetailsEditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    private function order(OrderStatus $status = OrderStatus::NewOrder): Order
    {
        return Order::factory()->create([
            'order_status' => $status,
            'payment_status' => PaymentStatus::Paid,
            'customer_name' => 'Aisha Binti Rahman',
            'address_line' => '12 Jalan Lama',
            'city' => 'Georgetown',
            'state' => 'MY-07',
            'postcode' => '10200',
            'subtotal_minor' => 13900,
            'shipping_fee_minor' => 800,
            'grand_total_minor' => 14700,
        ]);
    }

    /** @return array<string, string> */
    private function validDetails(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Aisha Binti Rahman',
            'customer_email' => 'aisha@example.test',
            'customer_phone' => '0123456789',
            'address_line' => '88 Jalan Baru',
            'city' => 'Bayan Lepas',
            'state' => 'MY-07',
            'postcode' => '11900',
        ], $overrides);
    }

    // ------------------------------------------------------------ access

    #[Test]
    public function the_form_opens_for_a_new_order(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.orders.edit', $this->order()))
            ->assertOk()
            ->assertSee('12 Jalan Lama');
    }

    #[Test]
    public function a_guest_cannot_open_the_form(): void
    {
        $this->get(route('admin.orders.edit', $this->order()))->assertRedirect();
    }

    #[Test]
    public function a_guest_cannot_submit_the_form(): void
    {
        $order = $this->order();

        $this->patch(route('admin.orders.details', $order), $this->validDetails())
            ->assertRedirect();

        $this->assertSame('12 Jalan Lama', $order->fresh()->address_line);
    }

    #[Test]
    public function the_edit_link_appears_on_a_new_order(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.orders.show', $this->order()))
            ->assertOk()
            ->assertSee('Edit customer &amp; address', false);
    }

    #[Test]
    public function the_edit_link_is_absent_on_an_order_being_processed(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.orders.show', $this->order(OrderStatus::Processing)))
            ->assertOk()
            ->assertDontSee('Edit customer &amp; address', false);
    }

    // ------------------------------------------------------------- saving

    #[Test]
    public function the_delivery_details_are_saved(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.details', $order), $this->validDetails())
            ->assertRedirect(route('admin.orders.show', $order));

        $order->refresh();

        $this->assertSame('88 Jalan Baru', $order->address_line);
        $this->assertSame('Bayan Lepas', $order->city);
        $this->assertSame('11900', $order->postcode);
        $this->assertSame('aisha@example.test', $order->customer_email);
    }

    #[Test]
    public function saving_moves_no_money_and_no_status(): void
    {
        // The whole point of the screen. A postcode edit that crosses to
        // another state does NOT re-quote delivery: the customer keeps the
        // figure they were charged.
        $order = $this->order();

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.details', $order), $this->validDetails([
                'state' => 'MY-12',
                'postcode' => '88000',
            ]))->assertRedirect();

        $order->refresh();

        $this->assertSame(13900, $order->subtotal_minor);
        $this->assertSame(800, $order->shipping_fee_minor);
        $this->assertSame(14700, $order->grand_total_minor);
        $this->assertSame(OrderStatus::NewOrder, $order->order_status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
    }

    #[Test]
    public function totals_posted_alongside_the_details_are_ignored(): void
    {
        // Order::$fillable excludes them, and the request does not validate
        // them. Both halves are asserted here because either alone would let
        // this through if the other were ever relaxed.
        $order = $this->order();

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.details', $order), $this->validDetails([
                'grand_total_minor' => 1,
                'subtotal_minor' => 1,
                'shipping_fee_minor' => 1,
                'payment_status' => PaymentStatus::Refunded->value,
                'order_status' => OrderStatus::Completed->value,
            ]))->assertRedirect();

        $order->refresh();

        $this->assertSame(14700, $order->grand_total_minor);
        $this->assertSame(13900, $order->subtotal_minor);
        $this->assertSame(800, $order->shipping_fee_minor);
        $this->assertSame(OrderStatus::NewOrder, $order->order_status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
    }

    // ------------------------------------------------------------- guards

    #[Test]
    public function a_pending_order_cannot_be_edited(): void
    {
        $order = $this->order(OrderStatus::Pending);

        $this->actingAs($this->admin)
            ->get(route('admin.orders.edit', $order))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.details', $order), $this->validDetails())
            ->assertRedirect();

        $this->assertSame('12 Jalan Lama', $order->fresh()->address_line);
    }

    #[Test]
    public function an_order_already_being_processed_cannot_be_edited(): void
    {
        $order = $this->order(OrderStatus::Processing);

        $this->actingAs($this->admin)
            ->get(route('admin.orders.edit', $order))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.details', $order), $this->validDetails())
            ->assertRedirect();

        $this->assertSame('12 Jalan Lama', $order->fresh()->address_line);
    }

    #[Test]
    public function an_order_with_a_booked_shipment_cannot_be_edited(): void
    {
        // The AWB is printed with the address it was booked against. Editing
        // the row afterwards does not redirect the parcel — it only makes the
        // label and the record disagree.
        $order = $this->order();
        Shipment::factory()->for($order)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.orders.edit', $order))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.details', $order), $this->validDetails())
            ->assertRedirect();

        $this->assertSame('12 Jalan Lama', $order->fresh()->address_line);
    }

    // --------------------------------------------------------- validation

    #[Test]
    public function a_malformed_postcode_is_rejected(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.details', $order), $this->validDetails(['postcode' => '123']))
            ->assertSessionHasErrors('postcode');

        $this->assertSame('10200', $order->fresh()->postcode);
    }

    #[Test]
    public function a_state_outside_the_list_is_rejected(): void
    {
        // Free-text states are what EasyParcel rejects at booking time, long
        // after the admin has stopped looking at this screen.
        $order = $this->order();

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.details', $order), $this->validDetails(['state' => 'Penang']))
            ->assertSessionHasErrors('state');

        $this->assertSame('MY-07', $order->fresh()->state);
    }

    #[Test]
    public function an_empty_address_is_rejected(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.details', $order), $this->validDetails(['address_line' => '']))
            ->assertSessionHasErrors('address_line');

        $this->assertSame('12 Jalan Lama', $order->fresh()->address_line);
    }
}
