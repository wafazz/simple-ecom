<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Nameset — a name and number printed on a jersey for an extra fee.
 *
 * The fee is charged PER SHIRT and is always re-read from the product, never
 * taken from the session or the request: what the customer types is theirs,
 * what it costs is the shop's.
 */
class NamesetTest extends TestCase
{
    use RefreshDatabase;

    private function jersey(bool $nameset = true, int $feeMinor = 1500, int $priceMinor = 9000): ProductVariant
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'name' => 'Home Kit',
            'category_id' => Category::factory()->create(['is_active' => true])->id,
            'nameset_enabled' => $nameset,
            'nameset_price_minor' => $nameset ? $feeMinor : 0,
        ]);

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'option1_name' => 'Size', 'option1_value' => 'M',
            'price_minor' => $priceMinor,
            'stock_qty' => 10,
        ]);
    }

    private function addToCart(ProductVariant $variant, array $extra = [], int $qty = 1)
    {
        return $this->post(route('cart.store'), array_merge([
            'variant_id' => $variant->id,
            'qty' => $qty,
        ], $extra));
    }

    private function withNameset(string $name = 'azlan', string $number = '10'): array
    {
        return ['nameset' => 1, 'nameset_name' => $name, 'nameset_number' => $number];
    }

    // --------------------------------------------------------------- admin

    #[Test]
    public function an_admin_turns_the_nameset_on_and_prices_it(): void
    {
        $variant = $this->jersey(nameset: false);
        $product = $variant->product;

        $this->actingAs(User::factory()->create())->put(route('admin.products.update', $product), [
            'category_id' => $product->category_id,
            'name' => $product->name,
            'description' => '',
            'is_active' => 1,
            'nameset_enabled' => 1,
            'nameset_price' => '15.00',
            'product_type' => 'variable',
            'variants' => [[
                'id' => $variant->id, 'sku' => $variant->sku, 'price' => '90.00',
                'stock_qty' => 10, 'weight_g' => 400, 'status' => 'active',
                'option1_name' => 'Size', 'option1_value' => 'M',
                'option2_name' => '', 'option2_value' => '',
            ]],
        ])->assertSessionHasNoErrors();

        $product->refresh();

        $this->assertTrue($product->nameset_enabled);
        $this->assertSame(1500, $product->nameset_price_minor);
    }

    #[Test]
    public function turning_the_nameset_off_clears_its_price(): void
    {
        $variant = $this->jersey(feeMinor: 1500);
        $product = $variant->product;

        $this->actingAs(User::factory()->create())->put(route('admin.products.update', $product), [
            'category_id' => $product->category_id,
            'name' => $product->name,
            'description' => '',
            'is_active' => 1,
            'nameset_enabled' => 0,
            'nameset_price' => '15.00',
            'product_type' => 'variable',
            'variants' => [[
                'id' => $variant->id, 'sku' => $variant->sku, 'price' => '90.00',
                'stock_qty' => 10, 'weight_g' => 400, 'status' => 'active',
                'option1_name' => 'Size', 'option1_value' => 'M',
                'option2_name' => '', 'option2_value' => '',
            ]],
        ])->assertSessionHasNoErrors();

        $product->refresh();

        // No product may advertise a fee nobody can buy.
        $this->assertFalse($product->nameset_enabled);
        $this->assertSame(0, $product->nameset_price_minor);
    }

    // ---------------------------------------------------------- storefront

    #[Test]
    public function the_option_appears_only_on_a_product_that_offers_it(): void
    {
        $with = $this->jersey();
        $without = $this->jersey(nameset: false);

        $this->get(route('products.show', $with->product))
            ->assertOk()->assertSee('Add nameset')->assertSee('15.00');

        $this->get(route('products.show', $without->product))
            ->assertOk()->assertDontSee('Add nameset');
    }

    // ---------------------------------------------------------------- cart

    #[Test]
    public function the_nameset_is_stored_and_priced_per_shirt(): void
    {
        $variant = $this->jersey(feeMinor: 1500, priceMinor: 9000);

        $this->addToCart($variant, $this->withNameset(), qty: 2);

        $line = app(CartService::class)->lines()->first();

        $this->assertSame(['name' => 'AZLAN', 'number' => '10'], $line->nameset);
        $this->assertSame(1500, $line->nameset_price_minor);
        // (90.00 + 15.00) x 2 — two shirts are two prints.
        $this->assertSame(21000, $line->line_total_minor);
    }

    #[Test]
    public function the_name_is_stored_in_capitals(): void
    {
        $variant = $this->jersey();

        $this->addToCart($variant, $this->withNameset('azlan bin ahmad', '7'));

        $this->assertSame(
            'AZLAN BIN AHMAD',
            app(CartService::class)->lines()->first()->nameset['name'],
        );
    }

    #[Test]
    public function no_nameset_means_no_extra_charge(): void
    {
        $variant = $this->jersey(priceMinor: 9000);

        $this->addToCart($variant);

        $line = app(CartService::class)->lines()->first();

        $this->assertNull($line->nameset);
        $this->assertSame(0, $line->nameset_price_minor);
        $this->assertSame(9000, $line->line_total_minor);
    }

    #[Test]
    public function a_nameset_posted_for_a_product_without_the_option_is_ignored(): void
    {
        $variant = $this->jersey(nameset: false, priceMinor: 9000);

        // A hand-made form. Otherwise this would put an unpriced instruction in
        // front of whoever packs the parcel.
        $this->addToCart($variant, $this->withNameset());

        $line = app(CartService::class)->lines()->first();

        $this->assertNull($line->nameset);
        $this->assertSame(9000, $line->line_total_minor);
    }

    #[Test]
    public function ticking_the_box_but_typing_nothing_adds_no_nameset(): void
    {
        $variant = $this->jersey();

        $this->addToCart($variant, ['nameset' => 1, 'nameset_name' => '', 'nameset_number' => '']);

        $this->assertNull(app(CartService::class)->lines()->first()->nameset);
    }

    #[Test]
    public function adding_the_same_jersey_again_keeps_its_nameset(): void
    {
        $variant = $this->jersey();

        $this->addToCart($variant, $this->withNameset());
        $this->addToCart($variant);

        $line = app(CartService::class)->lines()->first();

        $this->assertSame(2, $line->qty);
        $this->assertSame('AZLAN', $line->nameset['name'], 'A second shirt must not wipe the first name.');
    }

    #[Test]
    public function the_fee_follows_the_product_not_the_session(): void
    {
        $variant = $this->jersey(feeMinor: 1500, priceMinor: 9000);

        $this->addToCart($variant, $this->withNameset());

        // Repriced after the cart was filled.
        $variant->product->update(['nameset_price_minor' => 2000]);

        $this->assertSame(
            11000,
            app(CartService::class)->lines()->first()->line_total_minor,
        );
    }

    #[Test]
    public function turning_the_option_off_drops_a_nameset_already_in_a_cart(): void
    {
        $variant = $this->jersey(priceMinor: 9000);

        $this->addToCart($variant, $this->withNameset());
        $variant->product->update(['nameset_enabled' => false, 'nameset_price_minor' => 0]);

        $line = app(CartService::class)->lines()->first();

        $this->assertNull($line->nameset);
        $this->assertSame(9000, $line->line_total_minor);
    }

    #[Test]
    public function a_cart_saved_before_namesets_existed_still_works(): void
    {
        $variant = $this->jersey();

        // The old shape: variant_id => qty.
        session(['cart' => [$variant->id => 2]]);

        $line = app(CartService::class)->lines()->first();

        $this->assertSame(2, $line->qty);
        $this->assertNull($line->nameset);
    }

    // -------------------------------------------------------- validation

    #[Test]
    public function a_number_must_be_digits(): void
    {
        $this->addToCart($this->jersey(), $this->withNameset('AZLAN', '1O'))
            ->assertSessionHasErrors('nameset_number');
    }

    #[Test]
    public function a_name_may_not_carry_markup(): void
    {
        $this->addToCart($this->jersey(), $this->withNameset('<script>x</script>', '10'))
            ->assertSessionHasErrors('nameset_name');
    }

    // --------------------------------------------------------------- order

    #[Test]
    public function the_nameset_is_written_onto_the_order(): void
    {
        $variant = $this->jersey(feeMinor: 1500, priceMinor: 9000);

        $this->addToCart($variant, $this->withNameset('azlan', '10'), qty: 2);

        $this->post(route('checkout.store'), [
            'customer_name' => 'Faiz', 'customer_email' => 'faiz@example.com',
            'customer_phone' => '0123456789', 'address_line' => '1 Jalan Satu',
            'city' => 'George Town', 'state' => 'MY-07', 'postcode' => '10000',
            'country' => 'MY',
        ])->assertRedirect();

        $item = Order::latest('id')->first()->items->first();

        $this->assertSame('AZLAN', $item->nameset_name);
        $this->assertSame('10', $item->nameset_number);
        $this->assertSame(1500, $item->nameset_price_minor);
        // The garment alone stays separable from the print.
        $this->assertSame(9000, $item->unit_price_minor);
        $this->assertSame(21000, $item->line_total_minor);
    }

    #[Test]
    public function the_order_keeps_what_was_printed_even_if_the_product_changes(): void
    {
        $variant = $this->jersey();

        $this->addToCart($variant, $this->withNameset('azlan', '10'));
        $this->post(route('checkout.store'), [
            'customer_name' => 'Faiz', 'customer_email' => 'faiz@example.com',
            'customer_phone' => '0123456789', 'address_line' => '1 Jalan Satu',
            'city' => 'George Town', 'state' => 'MY-07', 'postcode' => '10000',
            'country' => 'MY',
        ]);

        $order = Order::latest('id')->first();
        $variant->product->update(['nameset_enabled' => false, 'nameset_price_minor' => 0]);

        // A snapshot, like product_name and unit_price_minor beside it.
        $this->assertSame('AZLAN', $order->items()->first()->fresh()->nameset_name);
    }

    #[Test]
    public function the_admin_order_screen_shows_what_to_print(): void
    {
        $variant = $this->jersey();

        $this->addToCart($variant, $this->withNameset('azlan', '10'));
        $this->post(route('checkout.store'), [
            'customer_name' => 'Faiz', 'customer_email' => 'faiz@example.com',
            'customer_phone' => '0123456789', 'address_line' => '1 Jalan Satu',
            'city' => 'George Town', 'state' => 'MY-07', 'postcode' => '10000',
            'country' => 'MY',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.orders.show', Order::latest('id')->first()))
            ->assertOk()
            ->assertSee('NAMESET')
            ->assertSee('AZLAN 10');
    }
}
