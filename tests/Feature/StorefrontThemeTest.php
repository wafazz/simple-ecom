<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The redesigned storefront — REQ-001 / REQ-003.
 *
 * The theme itself is not testable here, but its CONTRACTS are: every
 * interaction is progressive enhancement, so each one has markup that works
 * with JavaScript switched off. Those fallbacks are what these tests hold in
 * place, because they are exactly what a redesign quietly breaks.
 */
class StorefrontThemeTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        $product = Product::factory()->create($attributes);
        ProductVariant::factory()->for($product)->options('M', 'Black')
            ->create(['price_minor' => 3000, 'stock_qty' => 5]);

        return $product;
    }

    #[Test]
    public function the_gallery_shows_the_cover_first_then_the_extra_views(): void
    {
        $product = $this->product(['image_path' => 'products/cover.jpg']);
        ProductImage::factory()->for($product)->create(['path' => 'products/back.jpg', 'sort_order' => 1]);
        ProductImage::factory()->for($product)->create(['path' => 'products/detail.jpg', 'sort_order' => 2]);

        $urls = $product->fresh('images')->galleryUrls();

        // Order matters: the cover is what listings and the cart already show.
        $this->assertStringContainsString('cover.jpg', $urls[0]);
        $this->assertStringContainsString('back.jpg', $urls[1]);
        $this->assertStringContainsString('detail.jpg', $urls[2]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('back.jpg')
            ->assertSee('detail.jpg');
    }

    #[Test]
    public function a_product_with_no_cover_still_has_a_gallery(): void
    {
        // Regression guard: galleryUrls() must not return a null first entry
        // for a product whose only pictures are extra views.
        $product = $this->product(['image_path' => null]);
        ProductImage::factory()->for($product)->create(['path' => 'products/only.jpg']);

        $urls = $product->fresh('images')->galleryUrls();

        $this->assertCount(1, $urls);
        $this->assertStringContainsString('only.jpg', $urls[0]);
    }

    #[Test]
    public function deleting_a_product_takes_its_gallery_rows_with_it(): void
    {
        $product = $this->product();
        ProductImage::factory()->for($product)->create();

        $product->delete();

        $this->assertDatabaseCount('product_images', 0);
    }

    #[Test]
    public function the_variant_picker_falls_back_to_a_real_select(): void
    {
        // With JavaScript off the swatches do nothing, so the <select> is the
        // only way to choose — and it must carry price AND stock.
        $product = Product::factory()->create();
        ProductVariant::factory()->for($product)->options('M', 'Black')
            ->create(['price_minor' => 3000, 'stock_qty' => 4]);
        ProductVariant::factory()->for($product)->options('L', 'Black')
            ->create(['price_minor' => 3500, 'stock_qty' => 0]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('name="variant_id"', false)
            ->assertSee('RM30.00')
            ->assertSee('RM35.00')
            ->assertSee('In stock')
            ->assertSee('Out of stock');
    }

    #[Test]
    public function the_picker_never_receives_more_than_display_data(): void
    {
        // What reaches the browser is display-only. If a stock KEY leaked that
        // the cart trusted, tampering with it would matter — it does not,
        // because the cart re-reads both from the database (§17).
        $product = $this->product();

        $html = $this->get(route('products.show', $product))->assertOk()->getContent();

        $this->assertStringContainsString('data-variants=', $html);
        $this->assertStringNotContainsString('price_minor', $html);
        $this->assertStringNotContainsString('cost', $html);
    }

    #[Test]
    public function add_to_cart_answers_json_for_the_fetch_path(): void
    {
        $product = $this->product();
        $variant = $product->variants()->first();

        $this->postJson(route('cart.store'), ['variant_id' => $variant->id, 'qty' => 2])
            ->assertOk()
            ->assertJson(['ok' => true, 'cart_count' => 2])
            ->assertJsonStructure(['ok', 'message', 'cart_count']);
    }

    #[Test]
    public function add_to_cart_still_redirects_when_the_browser_posts_the_form(): void
    {
        // The same route, the same code path, a different audience. If this
        // ever stops working, JavaScript becomes a requirement for buying.
        $product = $this->product();

        $this->post(route('cart.store'), ['variant_id' => $product->variants()->first()->id])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('status');
    }

    #[Test]
    public function a_rejected_add_reports_the_reason_as_json(): void
    {
        $product = $this->product();
        $product->variants()->first()->update(['stock_qty' => 0]);

        $this->postJson(route('cart.store'), ['variant_id' => $product->variants()->first()->id])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    #[Test]
    public function products_can_be_sorted_by_price(): void
    {
        $cheap = $this->productPriced('Cheap', 1000);
        $dear = $this->productPriced('Dear', 9000);

        $html = $this->get(route('products.index', ['sort' => 'price_asc']))->assertOk()->getContent();
        $this->assertLessThan(strpos($html, $dear->name), strpos($html, $cheap->name));

        $html = $this->get(route('products.index', ['sort' => 'price_desc']))->assertOk()->getContent();
        $this->assertLessThan(strpos($html, $cheap->name), strpos($html, $dear->name));
    }

    #[Test]
    public function an_unknown_sort_value_falls_back_instead_of_reaching_the_query(): void
    {
        $this->productPriced('Anything', 1000);

        // A whitelist, not a passthrough: this would be SQL injection otherwise.
        $this->get(route('products.index', ['sort' => 'price_minor); drop table products;--']))
            ->assertOk()
            ->assertSee('Anything');

        $this->assertDatabaseCount('products', 1);
    }

    #[Test]
    public function products_can_be_searched_by_name(): void
    {
        $this->productPriced('Batik Shirt', 8900);
        $this->productPriced('Canvas Tote', 4500);

        $this->get(route('products.index', ['q' => 'batik']))
            ->assertOk()
            ->assertSee('Batik Shirt')
            ->assertDontSee('Canvas Tote');
    }

    #[Test]
    public function a_maximum_price_filters_on_the_cheapest_sellable_variant(): void
    {
        $this->productPriced('Under', 2000);
        $this->productPriced('Over', 12000);

        $this->get(route('products.index', ['max_price' => 50]))
            ->assertOk()
            ->assertSee('Under')
            ->assertDontSee('Over');
    }

    #[Test]
    public function the_search_box_and_filters_are_plain_forms(): void
    {
        // Both must submit without JavaScript. app.js only makes the filter
        // form submit on change; it is not what makes it work.
        $this->productPriced('Anything', 1000);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('data-filter-form', false)
            ->assertSee('data-filter-apply', false)
            ->assertSee('method="GET"', false);
    }

    private function productPriced(string $name, int $priceMinor): Product
    {
        $product = Product::factory()->create([
            'name' => $name,
            'category_id' => Category::factory(),
        ]);

        ProductVariant::factory()->for($product)->create([
            'price_minor' => $priceMinor, 'stock_qty' => 3,
        ]);

        return $product;
    }
}
