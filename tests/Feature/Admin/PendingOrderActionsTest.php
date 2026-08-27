<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\CheckoutController;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Approve / cancel / delete on an order that has not been paid for.
 *
 * The rule underneath all three is that an admin never decides whether money
 * arrived. Approving accepts an order for fulfilment and leaves the payment
 * status exactly where the gateway left it (§11.A.5), and neither cancel nor
 * delete will touch an order the customer has already paid.
 */
class PendingOrderActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    private function order(
        OrderStatus $status = OrderStatus::Pending,
        PaymentStatus $payment = PaymentStatus::Pending,
    ): Order {
        return Order::factory()->create(['order_status' => $status, 'payment_status' => $payment]);
    }

    // ------------------------------------------------------------- approve

    #[Test]
    public function approving_moves_a_pending_order_to_new_order(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.approve', $order))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(OrderStatus::NewOrder, $order->fresh()->order_status);
    }

    #[Test]
    public function approving_never_marks_an_order_paid(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin)->patch(route('admin.orders.approve', $order));

        // The whole point: payment is the gateway's word, never the admin's.
        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
    }

    #[Test]
    public function approving_says_the_money_is_still_outstanding(): void
    {
        $this->actingAs($this->admin)->patch(route('admin.orders.approve', $this->order()));

        $this->assertStringContainsString('still outstanding', (string) session('status'));
    }

    #[Test]
    public function an_order_that_is_not_pending_cannot_be_approved(): void
    {
        $order = $this->order(OrderStatus::Completed, PaymentStatus::Paid);

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.approve', $order))
            ->assertSessionHas('error');

        $this->assertSame(OrderStatus::Completed, $order->fresh()->order_status);
    }

    // -------------------------------------------------------------- cancel

    #[Test]
    public function cancelling_a_pending_order_works(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin)->patch(route('admin.orders.cancel', $order));

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->order_status);
    }

    #[Test]
    public function a_paid_order_is_refunded_not_cancelled(): void
    {
        $order = $this->order(OrderStatus::Pending, PaymentStatus::Paid);

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.cancel', $order))
            ->assertSessionHas('error');

        $this->assertSame(OrderStatus::Pending, $order->fresh()->order_status);
    }

    // -------------------------------------------------------------- delete

    #[Test]
    public function deleting_hides_the_order_but_keeps_the_record(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin)
            ->delete(route('admin.orders.destroy', $order))
            ->assertSessionHas('status');

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
        $this->assertNull(Order::find($order->id));
        $this->assertNotNull(Order::withTrashed()->find($order->id));
    }

    #[Test]
    public function a_deleted_order_leaves_the_admin_list(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin)->delete(route('admin.orders.destroy', $order));

        // Checked by the row's link, not the order number: the confirmation
        // flash names the order too, and would satisfy a bare text assertion
        // while the row was still sitting in the table.
        $this->actingAs($this->admin)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertDontSee(route('admin.orders.show', $order), false);
    }

    #[Test]
    public function a_paid_order_cannot_be_deleted(): void
    {
        $order = $this->order(OrderStatus::NewOrder, PaymentStatus::Paid);

        $this->actingAs($this->admin)
            ->delete(route('admin.orders.destroy', $order))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('orders', ['id' => $order->id]);
    }

    /**
     * The trap this feature could easily have shipped with.
     *
     * order_no is UNIQUE and a soft-deleted row keeps it. If the generator
     * counted only live rows, deleting an order would make the NEXT order reuse
     * a number that is still taken, and every checkout for the rest of the day
     * would fail on the unique key.
     */
    #[Test]
    public function deleting_an_order_does_not_break_the_next_order_number(): void
    {
        $first = Order::factory()->create(['order_no' => 'ORD-'.now()->format('Ymd').'-0001']);
        $second = Order::factory()->create(['order_no' => 'ORD-'.now()->format('Ymd').'-0002']);

        $this->actingAs($this->admin)->delete(route('admin.orders.destroy', $second));

        $controller = app(CheckoutController::class);
        $method = new \ReflectionMethod($controller, 'generateOrderNumber');
        $method->setAccessible(true);

        $next = $method->invoke($controller);

        $this->assertSame('ORD-'.now()->format('Ymd').'-0003', $next);
        $this->assertSame(0, Order::where('order_no', $next)->count(), 'The next number must be free.');
        $this->assertNotSame($second->order_no, $next, 'A deleted order must never have its number reissued.');
    }

    // --------------------------------------------------------------- access

    #[Test]
    public function a_guest_cannot_use_any_of_these(): void
    {
        $order = $this->order();

        $this->patch(route('admin.orders.approve', $order))->assertRedirect(route('admin.login'));
        $this->patch(route('admin.orders.cancel', $order))->assertRedirect(route('admin.login'));
        $this->delete(route('admin.orders.destroy', $order))->assertRedirect(route('admin.login'));

        $this->assertSame(OrderStatus::Pending, $order->fresh()->order_status);
        $this->assertNotSoftDeleted('orders', ['id' => $order->id]);
    }

    // ------------------------------------------------------------ the icons

    #[Test]
    public function the_row_offers_all_three_icons_for_a_pending_order(): void
    {
        $order = $this->order();

        $html = $this->actingAs($this->admin)
            ->get(route('admin.orders.index'))->assertOk()->getContent();

        foreach (['Approve order', 'Cancel order', 'Delete order'] as $label) {
            $this->assertStringContainsString(
                $label.' '.$order->order_no,
                $html,
                "The {$label} control has no accessible name.",
            );
        }

        $this->assertStringContainsString('bi-check-lg', $html);
        $this->assertStringContainsString('bi-x-lg', $html);
        $this->assertStringContainsString('bi-trash', $html);
    }

    #[Test]
    public function a_paid_order_offers_no_delete_icon(): void
    {
        $order = $this->order(OrderStatus::Pending, PaymentStatus::Paid);

        $this->actingAs($this->admin)
            ->get(route('admin.orders.index'))->assertOk()
            ->assertDontSee('Delete order '.$order->order_no);
    }

    #[Test]
    public function a_processing_order_offers_none_of_them(): void
    {
        $order = $this->order(OrderStatus::Processing, PaymentStatus::Paid);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.orders.index'))->assertOk()->getContent();

        foreach (['Approve order', 'Cancel order', 'Delete order'] as $label) {
            $this->assertStringNotContainsString($label.' '.$order->order_no, $html);
        }
    }
}
