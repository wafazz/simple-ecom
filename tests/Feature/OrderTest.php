<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-004 / REQ-007 — Planning §9.3, §11.A.5. */
class OrderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_paid_transition_succeeds_exactly_once(): void
    {
        // A duplicate ToyyibPay callback must be a no-op, not a second
        // settlement — this guard is what the stock decrement hangs off.
        $order = Order::factory()->create();

        $this->assertTrue(Order::markPaidAtomically($order->id));
        $this->assertFalse(Order::markPaidAtomically($order->id));

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Processing, $order->order_status);
    }

    #[Test]
    public function an_already_failed_order_cannot_be_flipped_to_paid_by_a_late_callback(): void
    {
        $order = Order::factory()->create();
        $order->forceFill(['payment_status' => PaymentStatus::Failed])->save();

        $this->assertFalse(Order::markPaidAtomically($order->id));
        $this->assertSame(PaymentStatus::Failed, $order->fresh()->payment_status);
    }

    #[Test]
    public function statuses_are_cast_to_enums_not_raw_strings(): void
    {
        $order = Order::factory()->create();

        $this->assertInstanceOf(OrderStatus::class, $order->order_status);
        $this->assertInstanceOf(PaymentStatus::class, $order->payment_status);
    }

    #[Test]
    public function totals_and_statuses_are_rejected_loudly_in_development(): void
    {
        // shouldBeStrict() is on outside production, so a request smuggling
        // these keys blows up in the developer's face instead of passing.
        $this->expectException(MassAssignmentException::class);

        (new Order)->fill([
            'order_no' => 'ORD-TAMPER-1',
            'grand_total_minor' => 1,
            'payment_status' => PaymentStatus::Paid->value,
        ]);
    }

    #[Test]
    public function totals_and_statuses_are_still_not_assigned_in_production(): void
    {
        // Production runs non-strict, where the keys are silently discarded.
        // The protection must hold there too — that is the mode that matters.
        Model::preventSilentlyDiscardingAttributes(false);

        try {
            $order = new Order;
            $order->fill([
                'order_no' => 'ORD-TAMPER-2',
                'customer_name' => 'Tamperer',
                'grand_total_minor' => 1,
                'subtotal_minor' => 1,
                'payment_status' => PaymentStatus::Paid->value,
                'order_status' => OrderStatus::Completed->value,
            ]);

            $this->assertNull($order->grand_total_minor);
            $this->assertNull($order->subtotal_minor);
            $this->assertNull($order->payment_status);
            $this->assertNull($order->order_status);
            $this->assertSame('Tamperer', $order->customer_name);
        } finally {
            Model::preventSilentlyDiscardingAttributes(true);
        }
    }

    #[Test]
    public function order_items_keep_their_snapshot_after_the_catalogue_price_changes(): void
    {
        $variant = ProductVariant::factory()->create(['price_minor' => 3000]);
        $order = Order::factory()->create();

        $item = OrderItem::factory()->for($order)->create([
            'product_variant_id' => $variant->id,
            'product_name' => 'T-Shirt',
            'variation_label' => 'M / Black',
            'sku' => 'TS-M-BLA',
            'unit_price_minor' => 3000,
            'qty' => 2,
            'line_total_minor' => 6000,
        ]);

        $variant->update(['price_minor' => 9999]);

        $item->refresh();
        $this->assertSame(3000, $item->unit_price_minor);
        $this->assertSame(6000, $item->line_total_minor);
        $this->assertSame('T-Shirt', $item->product_name);
    }

    #[Test]
    public function order_numbers_are_unique(): void
    {
        Order::factory()->create(['order_no' => 'ORD-20260826-0001']);

        $this->expectException(QueryException::class);

        Order::factory()->create(['order_no' => 'ORD-20260826-0001']);
    }

    #[Test]
    public function money_attributes_are_integers_in_php(): void
    {
        $order = Order::factory()->create([
            'subtotal_minor' => 3000,
            'shipping_fee_minor' => 1000,
            'grand_total_minor' => 4000,
        ])->fresh();

        $this->assertIsInt($order->subtotal_minor);
        $this->assertIsInt($order->grand_total_minor);
        $this->assertSame(4000, $order->subtotal_minor + $order->shipping_fee_minor);
    }
}
