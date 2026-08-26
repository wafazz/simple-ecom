<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-007 / REQ-009 — owner dashboard figures. */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    private function order(OrderStatus $orderStatus, PaymentStatus $paymentStatus, int $totalMinor, ?string $at = null): Order
    {
        $order = Order::factory()->create(['grand_total_minor' => $totalMinor]);

        $order->forceFill([
            'order_status' => $orderStatus,
            'payment_status' => $paymentStatus,
        ]);

        if ($at !== null) {
            $order->forceFill(['created_at' => $at]);
        }

        $order->save();

        return $order;
    }

    #[Test]
    public function total_sales_excludes_pending_and_cancelled_orders(): void
    {
        // The tile literally says "excl. pending & cancelled" — it must be true.
        $this->order(OrderStatus::Processing, PaymentStatus::Paid, 10000);
        $this->order(OrderStatus::Completed, PaymentStatus::Paid, 5000);
        $this->order(OrderStatus::Pending, PaymentStatus::Pending, 99999);
        $this->order(OrderStatus::Cancelled, PaymentStatus::Failed, 88888);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('totalSalesMinor', 15000);
    }

    #[Test]
    public function a_returned_order_is_not_revenue(): void
    {
        $this->order(OrderStatus::Completed, PaymentStatus::Paid, 10000);
        $this->order(OrderStatus::Returned, PaymentStatus::Paid, 30000);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('totalSalesMinor', 10000);
    }

    #[Test]
    public function a_needs_review_order_still_counts_as_a_sale(): void
    {
        // Money was taken; it simply cannot be fulfilled from stock yet.
        $this->order(OrderStatus::NeedsReview, PaymentStatus::Paid, 7000);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertViewHas('totalSalesMinor', 7000);
    }

    #[Test]
    public function total_collection_counts_only_settled_payments(): void
    {
        $this->order(OrderStatus::Processing, PaymentStatus::Paid, 10000);
        $this->order(OrderStatus::Processing, PaymentStatus::Pending, 60000);
        $this->order(OrderStatus::InDelivery, PaymentStatus::Refunded, 30000);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        // Sales counts the unpaid-but-live orders; collection does not.
        $response->assertViewHas('totalSalesMinor', 100000);
        $response->assertViewHas('totalCollectionMinor', 10000);
    }

    #[Test]
    public function average_order_value_is_the_mean_of_orders_that_count_as_sales(): void
    {
        $this->order(OrderStatus::Completed, PaymentStatus::Paid, 10000);
        $this->order(OrderStatus::Completed, PaymentStatus::Paid, 20000);
        // Neither of these is a sale, so neither may move the average.
        $this->order(OrderStatus::Pending, PaymentStatus::Pending, 999999);
        $this->order(OrderStatus::Cancelled, PaymentStatus::Failed, 999999);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('avgOrderValueMinor', 15000)
            ->assertViewHas('soldOrdersCount', 2);
    }

    #[Test]
    public function average_order_value_is_undefined_rather_than_zero_with_no_sales(): void
    {
        // An average of nothing is not RM 0.00.
        $this->order(OrderStatus::Pending, PaymentStatus::Pending, 5000);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('avgOrderValueMinor', null)
            ->assertSee('No sales yet');
    }

    #[Test]
    public function payment_conversion_is_the_share_of_orders_that_got_paid(): void
    {
        foreach (range(1, 8) as $i) {
            $this->order(OrderStatus::Completed, PaymentStatus::Paid, 1000);
        }
        $this->order(OrderStatus::Pending, PaymentStatus::Pending, 1000);
        $this->order(OrderStatus::Pending, PaymentStatus::Pending, 1000);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $this->assertEqualsWithDelta(80.0, $response->viewData('paymentConversion'), 0.01);
        $response->assertSee('80.0%')->assertSee('8 of 10 orders paid');
    }

    #[Test]
    public function payment_conversion_is_undefined_with_no_orders_at_all(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('paymentConversion', null)
            ->assertSee('No orders yet');
    }

    #[Test]
    public function money_awaiting_payment_is_surfaced_because_total_sales_excludes_it(): void
    {
        $this->order(OrderStatus::Completed, PaymentStatus::Paid, 10000);
        $this->order(OrderStatus::Pending, PaymentStatus::Pending, 4500);
        $this->order(OrderStatus::Pending, PaymentStatus::Pending, 2500);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('awaitingPaymentMinor', 7000);
        // Absent from the headline figure by definition, so it must appear
        // somewhere the owner will actually see it.
        $response->assertViewHas('totalSalesMinor', 10000);
        $response->assertSee('RM 70.00');
    }

    #[Test]
    public function period_comparisons_cover_day_week_month_and_year(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $labels = array_column($response->viewData('comparisons'), 'label');

        $this->assertSame([
            'Today vs Yesterday',
            'This Week vs Last Week',
            'This Month vs Last Month',
            'This Year vs Last Year',
        ], $labels);
    }

    #[Test]
    public function a_period_reports_the_change_against_the_previous_one(): void
    {
        // Yesterday 100.00, today 60.00 => down 40%.
        $this->order(OrderStatus::Processing, PaymentStatus::Paid, 10000, now()->subDay()->setTime(10, 0)->toDateTimeString());
        $this->order(OrderStatus::Processing, PaymentStatus::Paid, 6000, now()->setTime(10, 0)->toDateTimeString());

        $today = $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->viewData('comparisons')[0];

        $this->assertSame(6000, $today['sales_minor']);
        $this->assertEqualsWithDelta(-40.0, $today['sales_change'], 0.01);
    }

    #[Test]
    public function a_period_with_no_baseline_shows_no_percentage(): void
    {
        // Nothing yesterday: "up from zero" is not a percentage.
        $this->order(OrderStatus::Processing, PaymentStatus::Paid, 6000, now()->setTime(10, 0)->toDateTimeString());

        $today = $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->viewData('comparisons')[0];

        $this->assertNull($today['sales_change']);
    }

    #[Test]
    public function large_figures_are_grouped_and_headline_tiles_are_whole_ringgit(): void
    {
        $this->order(OrderStatus::Processing, PaymentStatus::Paid, 210784794);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('RM 2,107,848')                 // headline, rounded
            ->assertSee('RM 2,107,847.94');             // comparison card, exact
    }

    #[Test]
    public function a_guest_cannot_see_any_of_it(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }
}
