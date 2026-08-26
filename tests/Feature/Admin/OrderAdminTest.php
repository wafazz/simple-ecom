<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-007 */
class OrderAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    #[Test]
    public function a_guest_cannot_reach_order_screens(): void
    {
        $order = Order::factory()->create();

        $this->get(route('admin.orders.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.orders.show', $order))->assertRedirect(route('admin.login'));
        $this->patch(route('admin.orders.status', $order), ['order_status' => 'shipped'])
            ->assertRedirect(route('admin.login'));

        $this->assertSame(OrderStatus::Pending, $order->fresh()->order_status);
    }

    #[Test]
    public function the_list_shows_orders_and_can_be_filtered(): void
    {
        Order::factory()->create(['order_no' => 'ORD-A', 'customer_email' => 'alice@example.test']);
        Order::factory()->paid()->create(['order_no' => 'ORD-B', 'customer_email' => 'bob@example.test']);

        $this->actingAs($this->admin)->get(route('admin.orders.index'))
            ->assertOk()->assertSee('ORD-A')->assertSee('ORD-B');

        $this->actingAs($this->admin)
            ->get(route('admin.orders.index', ['payment_status' => 'paid']))
            ->assertOk()->assertSee('ORD-B')->assertDontSee('ORD-A');

        $this->actingAs($this->admin)
            ->get(route('admin.orders.index', ['q' => 'alice']))
            ->assertOk()->assertSee('ORD-A')->assertDontSee('ORD-B');
    }

    #[Test]
    public function the_detail_screen_shows_items_customer_payment_and_shipping(): void
    {
        $order = Order::factory()->paid()->create([
            'customer_name' => 'Aisha Rahman',
            'address_line' => '12 Jalan Contoh',
            'state' => 'MY-07',
            'courier_name' => 'J&T — Standard',
        ]);
        OrderItem::factory()->for($order)->create(['product_name' => 'T-Shirt', 'sku' => 'TS-M-BLA']);
        Payment::factory()->paid()->for($order)->create(['bill_code' => 'BILL123']);

        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('T-Shirt')
            ->assertSee('TS-M-BLA')
            ->assertSee('Aisha Rahman')
            ->assertSee('12 Jalan Contoh')
            ->assertSee('Pulau Pinang')
            ->assertSee('J&amp;T — Standard', false)
            ->assertSee('BILL123');
    }

    #[Test]
    public function an_order_using_the_flat_rate_says_so(): void
    {
        // The admin needs to know a courier outage priced this order.
        $order = Order::factory()->flatRate()->create();
        OrderItem::factory()->for($order)->create();

        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('flat rate');
    }

    #[Test]
    public function the_admin_can_advance_the_order_status(): void
    {
        $order = Order::factory()->paid()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.status', $order), ['order_status' => OrderStatus::InDelivery->value])
            ->assertRedirect();

        $this->assertSame(OrderStatus::InDelivery, $order->fresh()->order_status);
    }

    #[Test]
    public function an_invalid_order_status_is_rejected(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.status', $order), ['order_status' => 'teleported'])
            ->assertSessionHasErrors('order_status');

        $this->assertSame(OrderStatus::Pending, $order->fresh()->order_status);
    }

    #[Test]
    public function the_admin_cannot_mark_an_order_paid_by_hand(): void
    {
        // Payment status is gateway-driven. A hand-set "paid" would bypass the
        // server-side verification in Planning §11.A.5 entirely — so no route
        // accepts it, and the status form only offers order_status.
        $order = Order::factory()->create();

        $this->actingAs($this->admin)->patch(route('admin.orders.status', $order), [
            'order_status' => OrderStatus::Processing->value,
            'payment_status' => PaymentStatus::Paid->value,
        ])->assertRedirect();

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
    }

    #[Test]
    public function the_status_dropdown_offers_exactly_the_operational_statuses(): void
    {
        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertOk();

        foreach (['Pending', 'New Order', 'Processing', 'In Delivery', 'Completed', 'Returned', 'Cancelled'] as $label) {
            $response->assertSee($label);
        }
    }

    #[Test]
    public function needs_review_cannot_be_assigned_by_hand(): void
    {
        // It is a conclusion the system reaches when paid stock cannot be
        // allocated — not a state an admin picks.
        $order = Order::factory()->paid()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.status', $order), ['order_status' => OrderStatus::NeedsReview->value])
            ->assertSessionHasErrors('order_status');

        $this->assertSame(OrderStatus::NewOrder, $order->fresh()->order_status);
    }

    #[Test]
    public function an_order_already_in_needs_review_keeps_it_as_the_selected_option(): void
    {
        // Without this the dropdown would default to the first option, and
        // saving the form would silently reassign the order to Pending.
        $order = Order::factory()->paid()->create();
        $order->forceFill(['order_status' => OrderStatus::NeedsReview])->save();
        OrderItem::factory()->for($order)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Needs Review (current)');
    }

    #[Test]
    public function an_order_can_be_moved_through_the_full_fulfilment_path(): void
    {
        $order = Order::factory()->paid()->create();

        foreach ([OrderStatus::Processing, OrderStatus::InDelivery, OrderStatus::Completed] as $next) {
            $this->actingAs($this->admin)
                ->patch(route('admin.orders.status', $order), ['order_status' => $next->value])
                ->assertRedirect();

            $this->assertSame($next, $order->fresh()->order_status);
        }
    }

    #[Test]
    public function an_order_can_be_marked_returned(): void
    {
        $order = Order::factory()->paid()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.status', $order), ['order_status' => OrderStatus::Returned->value])
            ->assertRedirect();

        $this->assertSame(OrderStatus::Returned, $order->fresh()->order_status);
    }

    #[Test]
    public function a_paid_order_can_be_recorded_as_refunded(): void
    {
        $order = Order::factory()->paid()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.refund', $order))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(PaymentStatus::Refunded, $order->payment_status);
        $this->assertSame(OrderStatus::Cancelled, $order->order_status);
    }

    #[Test]
    public function an_unpaid_order_cannot_be_marked_refunded(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.refund', $order))
            ->assertSessionHas('error');

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
    }

    #[Test]
    public function orders_needing_review_are_surfaced_on_the_list(): void
    {
        Order::factory()->paid()->create(['order_status' => OrderStatus::NeedsReview]);

        $this->actingAs($this->admin)->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('need review');
    }
}
