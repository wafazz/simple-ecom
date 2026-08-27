<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * shop:reset-catalog --restore-stock must credit back only what was actually
 * taken off the shelf.
 *
 * Stock is decremented at SETTLEMENT (PaymentController), not at checkout, so a
 * pending or failed order never touched it. Crediting one back invents stock
 * that was never sold — the shop then oversells against a number nobody put
 * there.
 */
class ResetCatalogStockTest extends TestCase
{
    use RefreshDatabase;

    private function orderFor(ProductVariant $variant, PaymentStatus $status, int $qty): void
    {
        $order = Order::factory()->create(['payment_status' => $status]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'qty' => $qty,
        ]);
    }

    #[Test]
    public function only_settled_orders_give_their_stock_back(): void
    {
        $variant = ProductVariant::factory()->create(['stock_qty' => 10]);

        // Settled: three really left the shelf.
        $this->orderFor($variant, PaymentStatus::Paid, 3);
        ProductVariant::decrementStockAtomically($variant->id, 3);

        // Never settled: these took nothing.
        $this->orderFor($variant, PaymentStatus::Pending, 4);
        $this->orderFor($variant, PaymentStatus::Failed, 5);

        $this->assertSame(7, $variant->fresh()->stock_qty);

        $this->artisan('shop:reset-catalog', ['--orders-only' => true, '--restore-stock' => true, '--force' => true])
            ->assertSuccessful();

        // 10, the true original. Counting every line instead would give 19.
        $this->assertSame(10, $variant->fresh()->stock_qty);
    }

    #[Test]
    public function a_refunded_order_still_gives_its_stock_back(): void
    {
        $variant = ProductVariant::factory()->create(['stock_qty' => 10]);

        // The money went back to the customer; the goods did not come back to
        // the shelf, and nothing in the app ever returns them.
        $this->orderFor($variant, PaymentStatus::Refunded, 2);
        ProductVariant::decrementStockAtomically($variant->id, 2);

        $this->artisan('shop:reset-catalog', ['--orders-only' => true, '--restore-stock' => true, '--force' => true])
            ->assertSuccessful();

        $this->assertSame(10, $variant->fresh()->stock_qty);
    }

    #[Test]
    public function unpaid_orders_alone_change_no_stock_at_all(): void
    {
        $variant = ProductVariant::factory()->create(['stock_qty' => 6]);

        $this->orderFor($variant, PaymentStatus::Pending, 3);
        $this->orderFor($variant, PaymentStatus::Failed, 3);

        $this->artisan('shop:reset-catalog', ['--orders-only' => true, '--restore-stock' => true, '--force' => true])
            ->assertSuccessful();

        $this->assertSame(6, $variant->fresh()->stock_qty, 'Nothing was taken, so nothing may be returned.');
    }
}
