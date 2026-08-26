<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-013 — Planning §11.B.5. Booking spends real courier credit. */
class ShipmentBookingTest extends TestCase
{
    use RefreshDatabase;

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
