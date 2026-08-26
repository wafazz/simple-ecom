<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Setting;
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
        $this->order(OrderStatus::PendingPayment, PaymentStatus::Pending, 99999);
        $this->order(OrderStatus::Cancelled, PaymentStatus::Failed, 88888);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('totalSalesMinor', 15000);
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
        $this->order(OrderStatus::Shipped, PaymentStatus::Refunded, 30000);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        // Sales counts the unpaid-but-live orders; collection does not.
        $response->assertViewHas('totalSalesMinor', 100000);
        $response->assertViewHas('totalCollectionMinor', 10000);
    }

    #[Test]
    public function ads_cost_and_roas_read_not_tracked_when_no_spend_is_recorded(): void
    {
        // RM 0 would claim "we spent nothing", which is a different statement
        // from "we do not track this".
        $this->order(OrderStatus::Processing, PaymentStatus::Paid, 10000);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('roas', null)
            ->assertSee('Not tracked')
            ->assertDontSee('0.00x');
    }

    #[Test]
    public function roas_is_calculated_once_an_ads_cost_exists(): void
    {
        $this->order(OrderStatus::Processing, PaymentStatus::Paid, 210784794);
        Setting::put('ads_cost_minor', '63114800');

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('3.34x')
            ->assertDontSee('Not tracked');
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
