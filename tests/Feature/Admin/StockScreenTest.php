<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-008 — the stock screen adjusts quantities and nothing else. */
class StockScreenTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->product = Product::factory()->create();
    }

    #[Test]
    public function a_guest_cannot_reach_it(): void
    {
        $this->get(route('admin.products.variations.index', $this->product))
            ->assertRedirect(route('admin.login'));
    }

    #[Test]
    public function stock_can_be_adjusted(): void
    {
        $variant = ProductVariant::factory()->for($this->product)->create(['stock_qty' => 4]);

        $this->actingAs($this->admin)
            ->patch(route('admin.products.variations.stock', [$this->product, $variant]), ['stock_qty' => 25])
            ->assertRedirect();

        $this->assertSame(25, $variant->fresh()->stock_qty);
    }

    #[Test]
    public function negative_stock_is_rejected(): void
    {
        $variant = ProductVariant::factory()->for($this->product)->create(['stock_qty' => 4]);

        $this->actingAs($this->admin)
            ->patch(route('admin.products.variations.stock', [$this->product, $variant]), ['stock_qty' => -1])
            ->assertSessionHasErrors('stock_qty');

        $this->assertSame(4, $variant->fresh()->stock_qty);
    }

    #[Test]
    public function a_variant_from_another_product_cannot_be_touched_through_the_url(): void
    {
        $other = ProductVariant::factory()->create(['stock_qty' => 7]);

        $this->actingAs($this->admin)
            ->patch(route('admin.products.variations.stock', [$this->product, $other]), ['stock_qty' => 999])
            ->assertNotFound();

        $this->assertSame(7, $other->fresh()->stock_qty);
    }

    #[Test]
    public function it_no_longer_offers_a_way_to_create_a_variation(): void
    {
        // Variations are defined on the product form. Two places to create one
        // would be two places to get the option rules wrong.
        ProductVariant::factory()->for($this->product)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.products.variations.index', $this->product))
            ->assertOk()
            ->assertDontSee('Add a variation')
            ->assertSee('Edit product');
    }
}
