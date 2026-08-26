<?php

namespace Tests\Feature;

use App\Enums\VariantStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-003 — Planning §8. */
class CartTest extends TestCase
{
    use RefreshDatabase;

    private function variant(array $attributes = [], ?Product $product = null): ProductVariant
    {
        return ProductVariant::factory()
            ->for($product ?? Product::factory())
            ->create($attributes);
    }

    #[Test]
    public function the_same_product_in_two_variations_is_two_distinct_lines(): void
    {
        // Spec §10: T-Shirt/M/Black must not merge with T-Shirt/L/Black.
        $product = Product::factory()->create(['name' => 'T-Shirt']);
        $m = $this->variant(['price_minor' => 3000, 'stock_qty' => 5], $product);
        $l = ProductVariant::factory()->for($product)->options('L', 'Black')
            ->create(['price_minor' => 3200, 'stock_qty' => 5]);

        $this->post(route('cart.store'), ['variant_id' => $m->id, 'qty' => 1]);
        $this->post(route('cart.store'), ['variant_id' => $l->id, 'qty' => 1]);

        $this->assertCount(2, app(CartService::class)->lines());
        $this->assertSame(6200, app(CartService::class)->subtotalMinor());
    }

    #[Test]
    public function adding_the_same_variant_twice_increases_the_quantity(): void
    {
        $variant = $this->variant(['price_minor' => 1000, 'stock_qty' => 10]);

        $this->post(route('cart.store'), ['variant_id' => $variant->id, 'qty' => 2]);
        $this->post(route('cart.store'), ['variant_id' => $variant->id, 'qty' => 3]);

        $this->assertSame(5, app(CartService::class)->qtyFor($variant->id));
        $this->assertSame(5000, app(CartService::class)->subtotalMinor());
    }

    #[Test]
    public function quantity_is_capped_at_available_stock(): void
    {
        $variant = $this->variant(['price_minor' => 1000, 'stock_qty' => 3]);

        $this->post(route('cart.store'), ['variant_id' => $variant->id, 'qty' => 99]);

        $this->assertSame(3, app(CartService::class)->qtyFor($variant->id));
    }

    #[Test]
    public function an_out_of_stock_variant_cannot_be_added(): void
    {
        $variant = $this->variant(['stock_qty' => 0]);

        $this->post(route('cart.store'), ['variant_id' => $variant->id, 'qty' => 1])
            ->assertSessionHas('error');

        $this->assertTrue(app(CartService::class)->isEmpty());
    }

    #[Test]
    public function an_inactive_variant_cannot_be_added(): void
    {
        $variant = $this->variant(['status' => VariantStatus::Inactive, 'stock_qty' => 5]);

        $this->post(route('cart.store'), ['variant_id' => $variant->id, 'qty' => 1])
            ->assertSessionHas('error');

        $this->assertTrue(app(CartService::class)->isEmpty());
    }

    #[Test]
    public function quantity_can_be_updated_and_zero_removes_the_line(): void
    {
        $variant = $this->variant(['price_minor' => 1000, 'stock_qty' => 10]);
        $this->post(route('cart.store'), ['variant_id' => $variant->id, 'qty' => 2]);

        $this->patch(route('cart.update', $variant->id), ['qty' => 7]);
        $this->assertSame(7, app(CartService::class)->qtyFor($variant->id));

        $this->patch(route('cart.update', $variant->id), ['qty' => 0]);
        $this->assertTrue(app(CartService::class)->isEmpty());
    }

    #[Test]
    public function an_item_can_be_removed(): void
    {
        $variant = $this->variant(['stock_qty' => 5]);
        $this->post(route('cart.store'), ['variant_id' => $variant->id, 'qty' => 1]);

        $this->delete(route('cart.destroy', $variant->id));

        $this->assertTrue(app(CartService::class)->isEmpty());
    }

    #[Test]
    public function the_price_is_re_read_from_the_database_not_the_session(): void
    {
        // The whole reason the session stores only variant_id => qty
        // (Planning §8). A price change must be picked up immediately.
        $variant = $this->variant(['price_minor' => 1000, 'stock_qty' => 10]);
        $this->post(route('cart.store'), ['variant_id' => $variant->id, 'qty' => 2]);

        $this->assertSame(2000, app(CartService::class)->subtotalMinor());

        $variant->update(['price_minor' => 1500]);

        $this->assertSame(3000, app(CartService::class)->subtotalMinor());
    }

    #[Test]
    public function a_deactivated_variant_is_pruned_from_the_cart(): void
    {
        $variant = $this->variant(['price_minor' => 1000, 'stock_qty' => 5]);
        $this->post(route('cart.store'), ['variant_id' => $variant->id, 'qty' => 2]);

        $variant->update(['status' => VariantStatus::Inactive]);

        $this->assertCount(0, app(CartService::class)->lines());
        $this->assertTrue(app(CartService::class)->isEmpty());
    }

    #[Test]
    public function a_line_is_clamped_when_stock_falls_after_it_was_added(): void
    {
        $variant = $this->variant(['price_minor' => 1000, 'stock_qty' => 10]);
        $this->post(route('cart.store'), ['variant_id' => $variant->id, 'qty' => 8]);

        $variant->update(['stock_qty' => 3]);

        $line = app(CartService::class)->lines()->first();

        $this->assertSame(3, $line->qty);
        $this->assertTrue($line->reduced);
        $this->assertSame(3000, $line->line_total_minor);
    }

    #[Test]
    public function total_weight_falls_back_to_the_store_default_for_a_weightless_variant(): void
    {
        // A quotation must never be requested at zero weight (OQ-01).
        $variant = $this->variant(['weight_g' => 0, 'stock_qty' => 5]);
        $this->post(route('cart.store'), ['variant_id' => $variant->id, 'qty' => 2]);

        $this->assertSame(2 * config('shop.default_weight_g'), app(CartService::class)->totalWeightG());
    }

    #[Test]
    public function the_cart_page_renders_when_empty(): void
    {
        $this->get(route('cart.index'))->assertOk()->assertSee('Your cart is empty');
    }
}
