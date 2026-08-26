<?php

namespace Tests\Feature;

use App\Enums\VariantStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-001 / REQ-002 — storefront. */
class CatalogueBrowsingTest extends TestCase
{
    use RefreshDatabase;

    private function sellableProduct(array $attributes = []): Product
    {
        $product = Product::factory()->create($attributes);
        ProductVariant::factory()->for($product)->create(['price_minor' => 3000]);

        return $product;
    }

    #[Test]
    public function the_listing_shows_active_products(): void
    {
        $this->sellableProduct(['name' => 'Visible Tee']);

        $this->get(route('products.index'))->assertOk()->assertSee('Visible Tee');
    }

    #[Test]
    public function an_inactive_product_is_hidden_from_the_listing_and_returns_404(): void
    {
        $product = $this->sellableProduct(['name' => 'Hidden Tee', 'is_active' => false]);

        $this->get(route('products.index'))->assertOk()->assertDontSee('Hidden Tee');
        $this->get(route('products.show', $product))->assertNotFound();
    }

    #[Test]
    public function a_product_with_no_sellable_variant_is_not_listed(): void
    {
        // Price and stock live on the variant, so a product with none is not
        // something a customer can order (Planning §7).
        Product::factory()->create(['name' => 'Variantless']);

        $this->get(route('products.index'))->assertOk()->assertDontSee('Variantless');
    }

    #[Test]
    public function a_product_whose_variants_are_all_inactive_is_not_listed(): void
    {
        $product = Product::factory()->create(['name' => 'Retired Tee']);
        ProductVariant::factory()->for($product)->create(['status' => VariantStatus::Inactive]);

        $this->get(route('products.index'))->assertOk()->assertDontSee('Retired Tee');
        $this->get(route('products.show', $product))->assertNotFound();
    }

    #[Test]
    public function the_listing_can_be_filtered_by_category(): void
    {
        $apparel = Category::factory()->create(['name' => 'Apparel', 'slug' => 'apparel']);
        $bags = Category::factory()->create(['name' => 'Bags', 'slug' => 'bags']);

        $this->sellableProduct(['name' => 'Cotton Tee', 'category_id' => $apparel->id]);
        $this->sellableProduct(['name' => 'Canvas Tote', 'category_id' => $bags->id]);

        $this->get(route('products.index', ['category' => 'apparel']))
            ->assertOk()
            ->assertSee('Cotton Tee')
            ->assertDontSee('Canvas Tote');
    }

    #[Test]
    public function the_listing_shows_the_cheapest_sellable_variant_price(): void
    {
        $product = Product::factory()->create(['name' => 'Tee']);
        ProductVariant::factory()->for($product)->options('L')->create(['price_minor' => 3200]);
        ProductVariant::factory()->for($product)->options('S')->create(['price_minor' => 2800]);

        $this->get(route('products.index'))->assertOk()->assertSee('RM28.00');
    }

    #[Test]
    public function the_detail_page_lists_each_combination_with_its_own_price_and_stock(): void
    {
        $product = Product::factory()->create(['name' => 'T-Shirt']);
        ProductVariant::factory()->for($product)->options('M', 'Black')->create([
            'price_minor' => 3000, 'stock_qty' => 20,
        ]);
        ProductVariant::factory()->for($product)->options('L', 'Black')->create([
            'price_minor' => 3200, 'stock_qty' => 0,
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('RM30.00')
            ->assertSee('RM32.00')
            ->assertSee('In stock')
            ->assertSee('Out of stock');
    }

    #[Test]
    public function an_inactive_variant_is_not_offered_on_the_detail_page(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->for($product)->options('M')->create(['price_minor' => 3000]);
        ProductVariant::factory()->for($product)->options('XL')->create([
            'price_minor' => 9900, 'status' => VariantStatus::Inactive,
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('RM30.00')
            ->assertDontSee('RM99.00');
    }

    #[Test]
    public function the_detail_page_is_reached_by_slug(): void
    {
        $product = $this->sellableProduct(['name' => 'Cotton Tee', 'slug' => 'cotton-tee']);

        $this->get('/products/cotton-tee')->assertOk()->assertSee('Cotton Tee');
        $this->get('/products/'.$product->id)->assertNotFound();
    }
}
