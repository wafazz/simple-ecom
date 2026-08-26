<?php

namespace Tests\Feature;

use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-008 — Planning §7.5, the Atomic Race-Free Action Guard. */
class InventoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function stock_decrements_when_sufficient(): void
    {
        $variant = ProductVariant::factory()->create(['stock_qty' => 10]);

        $this->assertTrue(ProductVariant::decrementStockAtomically($variant->id, 3));
        $this->assertSame(7, $variant->fresh()->stock_qty);
    }

    #[Test]
    public function decrement_is_refused_when_stock_is_insufficient(): void
    {
        $variant = ProductVariant::factory()->create(['stock_qty' => 2]);

        $this->assertFalse(ProductVariant::decrementStockAtomically($variant->id, 3));

        // Refused means untouched — not clamped to zero, not negative.
        $this->assertSame(2, $variant->fresh()->stock_qty);
    }

    #[Test]
    public function stock_can_be_taken_to_exactly_zero_but_no_further(): void
    {
        $variant = ProductVariant::factory()->create(['stock_qty' => 5]);

        $this->assertTrue(ProductVariant::decrementStockAtomically($variant->id, 5));
        $this->assertSame(0, $variant->fresh()->stock_qty);

        $this->assertFalse(ProductVariant::decrementStockAtomically($variant->id, 1));
        $this->assertSame(0, $variant->fresh()->stock_qty);
    }

    #[Test]
    public function only_one_of_two_competing_claims_on_the_last_unit_succeeds(): void
    {
        // The oversell scenario from Planning §7.5: two customers both reach the
        // gateway for the last unit. Exactly one decrement may win.
        $variant = ProductVariant::factory()->create(['stock_qty' => 1]);

        $first = ProductVariant::decrementStockAtomically($variant->id, 1);
        $second = ProductVariant::decrementStockAtomically($variant->id, 1);

        $this->assertTrue($first);
        $this->assertFalse($second);
        $this->assertSame(0, $variant->fresh()->stock_qty);
    }

    #[Test]
    public function an_out_of_stock_variant_is_not_purchasable(): void
    {
        $this->assertFalse(ProductVariant::factory()->outOfStock()->create()->isPurchasable());
        $this->assertTrue(ProductVariant::factory()->create(['stock_qty' => 1])->isPurchasable());
    }
}
