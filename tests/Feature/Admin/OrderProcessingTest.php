<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Moving orders to Processing, one at a time or in bulk — REQ-007.
 *
 * The row button and the bulk bar post to the same action, so these tests
 * cover both. What they mostly guard is what must NOT move: the action reaches
 * many orders at once, which is exactly when a too-permissive rule does real
 * damage before anyone notices.
 */
class OrderProcessingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    private function order(OrderStatus $status): Order
    {
        return Order::factory()->create([
            'order_status' => $status,
            'payment_status' => $status === OrderStatus::Pending
                ? PaymentStatus::Pending
                : PaymentStatus::Paid,
        ]);
    }

    private function move(array $ids)
    {
        return $this->actingAs($this->admin)
            ->patch(route('admin.orders.process'), ['order_ids' => $ids]);
    }

    #[Test]
    public function a_single_new_order_moves_to_processing(): void
    {
        $order = $this->order(OrderStatus::NewOrder);

        $this->move([$order->id])
            ->assertRedirect()
            ->assertSessionHas('status', '1 order moved to Processing.');

        $this->assertSame(OrderStatus::Processing, $order->fresh()->order_status);
    }

    #[Test]
    public function many_orders_move_in_one_request(): void
    {
        $orders = Order::factory()->count(3)->create([
            'order_status' => OrderStatus::NewOrder,
            'payment_status' => PaymentStatus::Paid,
        ]);

        $this->move($orders->pluck('id')->all())
            ->assertSessionHas('status', '3 orders moved to Processing.');

        foreach ($orders as $order) {
            $this->assertSame(OrderStatus::Processing, $order->fresh()->order_status);
        }
    }

    #[Test]
    public function only_a_new_order_may_be_moved(): void
    {
        // The exclusions are the whole point. Pending is unpaid; the last four
        // would all be moving backwards.
        foreach ([
            OrderStatus::Pending,
            OrderStatus::Processing,
            OrderStatus::InDelivery,
            OrderStatus::Completed,
            OrderStatus::Returned,
            OrderStatus::Cancelled,
            OrderStatus::NeedsReview,
        ] as $status) {
            $order = $this->order($status);

            $this->move([$order->id]);

            $this->assertSame(
                $status,
                $order->fresh()->order_status,
                "{$status->value} must not be movable to Processing"
            );
        }
    }

    #[Test]
    public function a_mixed_selection_moves_what_it_can_and_says_what_it_skipped(): void
    {
        // A partial result an admin cannot see is one they assume was total.
        $new = $this->order(OrderStatus::NewOrder);
        $cancelled = $this->order(OrderStatus::Cancelled);
        $delivered = $this->order(OrderStatus::InDelivery);

        $this->move([$new->id, $cancelled->id, $delivered->id])
            ->assertSessionHas('status', '1 order moved to Processing. 2 skipped — only a New Order can be moved.');

        $this->assertSame(OrderStatus::Processing, $new->fresh()->order_status);
        $this->assertSame(OrderStatus::Cancelled, $cancelled->fresh()->order_status);
    }

    #[Test]
    public function moving_nothing_reports_an_error_rather_than_success(): void
    {
        $order = $this->order(OrderStatus::Completed);

        // An error, and NOT a success message beside it — a green "moved"
        // toast on a request that moved nothing is worse than no feedback.
        $this->move([$order->id])
            ->assertSessionHas('error')
            ->assertSessionMissing('status');
    }

    #[Test]
    public function a_duplicated_id_is_counted_once(): void
    {
        // Otherwise the same order ticked twice reports "2 moved, 1 skipped".
        $order = $this->order(OrderStatus::NewOrder);

        $this->move([$order->id, $order->id, $order->id])
            ->assertSessionHas('status', '1 order moved to Processing.');
    }

    #[Test]
    public function an_unknown_id_changes_nothing(): void
    {
        $this->move([999999])->assertSessionHas('error');
        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function a_guest_cannot_move_orders(): void
    {
        $order = $this->order(OrderStatus::NewOrder);

        $this->patch(route('admin.orders.process'), ['order_ids' => [$order->id]])
            ->assertRedirect(route('admin.login'));

        $this->assertSame(OrderStatus::NewOrder, $order->fresh()->order_status);
    }

    #[Test]
    public function an_empty_selection_is_rejected_by_validation(): void
    {
        $this->move([])->assertSessionHasErrors('order_ids');
    }

    #[Test]
    public function the_listing_offers_a_checkbox_and_a_button_only_for_new_orders(): void
    {
        $new = $this->order(OrderStatus::NewOrder);
        $done = $this->order(OrderStatus::Completed);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.orders.index', ['order_status' => 'new_order']))
            ->assertOk()
            ->assertSee('data-select-all', false)
            ->assertSee('Move to Processing')
            ->getContent();

        $this->assertStringContainsString('value="'.$new->id.'"', $html);

        // A page with nothing movable offers no select-all at all.
        $this->actingAs($this->admin)
            ->get(route('admin.orders.index', ['order_status' => 'completed']))
            ->assertOk()
            ->assertDontSee('data-select-all', false)
            ->assertDontSee('Move to Processing');

        $this->assertSame(OrderStatus::Completed, $done->fresh()->order_status);
    }

    #[Test]
    public function the_checkboxes_work_without_javascript(): void
    {
        // Every control is a real form input posting to the same route; the
        // script only adds select-all and the count.
        $this->order(OrderStatus::NewOrder);

        $this->actingAs($this->admin)
            ->get(route('admin.orders.index', ['order_status' => 'new_order']))
            ->assertOk()
            ->assertSee('name="order_ids[]"', false)
            ->assertSee('id="bulk-process"', false)
            ->assertSee('form="bulk-process"', false);
    }
}
