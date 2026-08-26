<?php

namespace Tests\Feature;

use App\Enums\VariantStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * REQ-010 — spec §17's "do not trust" list, exercised directly.
 *
 * Client-side prices · stock · totals · browser redirects · hidden form fields
 * · query parameters. Each is attacked here rather than assumed safe.
 */
class SecurityManipulationTest extends TestCase
{
    use RefreshDatabase;

    private function details(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Attacker',
            'customer_email' => 'a@b.test',
            'customer_phone' => '0123456789',
            'address_line' => '12 Jalan',
            'city' => 'KL',
            'state' => 'MY-14',
            'postcode' => '50000',
            'country' => 'MY',
        ], $overrides);
    }

    #[Test]
    public function a_hidden_price_field_cannot_change_what_is_charged(): void
    {
        $variant = ProductVariant::factory()->create(['price_minor' => 5000, 'stock_qty' => 5]);
        $this->post(route('cart.store'), ['variant_id' => $variant->id, 'qty' => 1]);
        Setting::put('flat_shipping_fee_minor', '1000');

        $this->post(route('checkout.store'), $this->details([
            'price_minor' => 1,
            'unit_price_minor' => 1,
            'subtotal_minor' => 1,
            'grand_total_minor' => 1,
            'shipping_fee_minor' => 0,
        ]));

        $order = Order::firstOrFail();
        $this->assertSame(5000, $order->subtotal_minor);
        $this->assertSame(1000, $order->shipping_fee_minor);
        $this->assertSame(6000, $order->grand_total_minor);
        $this->assertSame(5000, $order->items()->firstOrFail()->unit_price_minor);
    }

    #[Test]
    public function stock_cannot_be_exceeded_by_posting_a_large_quantity(): void
    {
        $variant = ProductVariant::factory()->create(['stock_qty' => 3]);

        $this->post(route('cart.store'), ['variant_id' => $variant->id, 'qty' => 100]);

        $this->assertSame(3, app(CartService::class)->qtyFor($variant->id));
    }

    #[Test]
    public function a_negative_quantity_is_rejected_outright(): void
    {
        $variant = ProductVariant::factory()->create(['stock_qty' => 5]);

        $this->post(route('cart.store'), ['variant_id' => $variant->id, 'qty' => -5])
            ->assertSessionHasErrors('qty');

        $this->assertTrue(app(CartService::class)->isEmpty());
    }

    #[Test]
    public function a_variant_id_for_an_inactive_product_cannot_be_forced_into_the_cart(): void
    {
        // Guessing an id must not bypass the sellability check.
        $product = Product::factory()->create(['is_active' => false]);
        $variant = ProductVariant::factory()->for($product)->create(['stock_qty' => 10]);

        $this->post(route('cart.store'), ['variant_id' => $variant->id, 'qty' => 1])
            ->assertSessionHas('error');

        $this->assertTrue(app(CartService::class)->isEmpty());
    }

    #[Test]
    public function a_nonexistent_variant_id_does_not_error_out(): void
    {
        $this->post(route('cart.store'), ['variant_id' => 999999, 'qty' => 1])
            ->assertSessionHas('error');

        $this->assertTrue(app(CartService::class)->isEmpty());
    }

    #[Test]
    public function a_deactivated_variant_cannot_be_carried_through_checkout(): void
    {
        $variant = ProductVariant::factory()->create(['price_minor' => 3000, 'stock_qty' => 5]);
        $this->post(route('cart.store'), ['variant_id' => $variant->id, 'qty' => 2]);

        // Deactivated after it was added.
        $variant->update(['status' => VariantStatus::Inactive]);

        $this->post(route('checkout.store'), $this->details())
            ->assertRedirect(route('products.index'));

        $this->assertSame(0, Order::count());
    }

    #[Test]
    public function an_order_cannot_be_read_by_guessing_its_number(): void
    {
        $order = Order::factory()->create([
            'order_no' => 'ORD-20260826-0001',
            'customer_email' => 'victim@example.test',
            'customer_name' => 'Victim Name',
            'address_line' => '99 Secret Road',
        ]);

        $this->post(route('order-status.lookup'), [
            'order_no' => $order->order_no,
            'email' => 'attacker@example.test',
        ])->assertOk()
            ->assertDontSee('Victim Name')
            ->assertDontSee('99 Secret Road');
    }

    #[Test]
    public function order_detail_is_admin_only(): void
    {
        $order = Order::factory()->create();

        $this->get(route('admin.orders.show', $order))->assertRedirect(route('admin.login'));
    }

    #[Test]
    public function a_deactivated_admin_loses_access_immediately(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.orders.index'))->assertOk();

        $admin->update(['is_active' => false]);

        $this->actingAs($admin)->get(route('admin.orders.index'))->assertRedirect(route('admin.login'));
    }

    #[Test]
    public function session_cookies_are_configured_defensively(): void
    {
        $this->assertTrue((bool) config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertTrue((bool) config('session.encrypt'));
    }

    #[Test]
    public function the_public_directory_exposes_no_application_files(): void
    {
        // The web root must contain assets and the front controller only —
        // .env, app code and storage live above it (spec §33).
        $public = base_path('public');

        foreach (['.env', 'composer.json', 'artisan'] as $forbidden) {
            $this->assertFileDoesNotExist($public.'/'.$forbidden);
        }

        $this->assertFileExists($public.'/index.php');
    }
}
