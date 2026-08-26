<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-004 — Planning §9. */
class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function details(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Aisha Rahman',
            'customer_email' => 'aisha@example.test',
            'customer_phone' => '0123456789',
            'address_line' => '12 Jalan Contoh',
            'city' => 'Kuala Lumpur',
            'state' => 'MY-14',
            'postcode' => '50000',
            'country' => 'MY',
        ], $overrides);
    }

    private int $skuSeq = 0;

    private function cartWith(int $priceMinor = 3000, int $qty = 2, int $stock = 10, string $sku = 'TS-M-BLA'): ProductVariant
    {
        // SKU is globally unique, so repeated calls need distinct values.
        $sku = $this->skuSeq++ === 0 ? $sku : $sku.'-'.$this->skuSeq;

        $product = Product::factory()->create(['name' => 'T-Shirt']);
        $variant = ProductVariant::factory()->for($product)->options('M', 'Black')
            ->create(['price_minor' => $priceMinor, 'stock_qty' => $stock, 'sku' => $sku]);

        $this->post(route('cart.store'), ['variant_id' => $variant->id, 'qty' => $qty]);

        return $variant;
    }

    #[Test]
    public function checkout_redirects_away_when_the_cart_is_empty(): void
    {
        $this->get(route('checkout.create'))->assertRedirect(route('products.index'));
        $this->post(route('checkout.store'), $this->details())->assertRedirect(route('products.index'));

        $this->assertSame(0, Order::count());
    }

    #[Test]
    public function an_order_is_created_pending_payment(): void
    {
        $this->cartWith();

        $this->post(route('checkout.store'), $this->details())->assertRedirect();

        $order = Order::firstOrFail();

        $this->assertSame(OrderStatus::PendingPayment, $order->order_status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{4}$/', $order->order_no);
    }

    #[Test]
    public function totals_are_computed_server_side_and_posted_amounts_are_ignored(): void
    {
        // The core price-tampering control (spec §17).
        $this->cartWith(priceMinor: 3000, qty: 2);
        Setting::put('flat_shipping_fee_minor', '1000');

        $this->post(route('checkout.store'), $this->details([
            'subtotal_minor' => 1,
            'shipping_fee_minor' => 0,
            'grand_total_minor' => 1,
        ]));

        $order = Order::firstOrFail();

        $this->assertSame(6000, $order->subtotal_minor);
        $this->assertSame(1000, $order->shipping_fee_minor);
        $this->assertSame(7000, $order->grand_total_minor);
    }

    #[Test]
    public function order_items_snapshot_the_purchase_details(): void
    {
        $variant = $this->cartWith(priceMinor: 3000, qty: 2);

        $this->post(route('checkout.store'), $this->details());

        $item = Order::firstOrFail()->items()->firstOrFail();

        $this->assertSame('T-Shirt', $item->product_name);
        $this->assertSame('M / Black', $item->variation_label);
        $this->assertSame('TS-M-BLA', $item->sku);
        $this->assertSame(3000, $item->unit_price_minor);
        $this->assertSame(2, $item->qty);
        $this->assertSame(6000, $item->line_total_minor);

        // The snapshot must survive a later catalogue change.
        $variant->update(['price_minor' => 9999]);
        $this->assertSame(3000, $item->fresh()->unit_price_minor);
    }

    #[Test]
    public function stock_is_not_decremented_at_order_creation(): void
    {
        // Spec §15 / Planning §7.5: stock is checked, not held. The decrement
        // happens once inside the verified-payment transaction in Phase 7.
        $variant = $this->cartWith(qty: 2, stock: 10);

        $this->post(route('checkout.store'), $this->details());

        $this->assertSame(10, $variant->fresh()->stock_qty);
    }

    #[Test]
    public function the_cart_is_emptied_after_the_order_is_created(): void
    {
        $this->cartWith();

        $this->post(route('checkout.store'), $this->details());

        $this->assertTrue(app(CartService::class)->isEmpty());
    }

    #[Test]
    public function the_customer_details_are_validated(): void
    {
        $this->cartWith();

        $this->post(route('checkout.store'), $this->details([
            'customer_email' => 'not-an-email',
            'postcode' => 'ABC',
            'state' => 'Selangor',
        ]))->assertSessionHasErrors(['customer_email', 'postcode', 'state']);

        $this->assertSame(0, Order::count());
    }

    #[Test]
    public function a_free_text_state_is_rejected_in_favour_of_the_iso_code(): void
    {
        // EasyParcel needs MY-10, not "Selangor" (Planning §11.B.1).
        $this->cartWith();

        $this->post(route('checkout.store'), $this->details(['state' => 'MY-10']))
            ->assertSessionHasNoErrors();

        $this->assertSame('MY-10', Order::firstOrFail()->state);
    }

    #[Test]
    public function order_numbers_are_sequential_within_the_day(): void
    {
        $this->cartWith();
        $this->post(route('checkout.store'), $this->details());

        $this->cartWith();
        $this->post(route('checkout.store'), $this->details());

        $numbers = Order::orderBy('id')->pluck('order_no')->all();

        $this->assertCount(2, array_unique($numbers));
        $this->assertStringEndsWith('-0001', $numbers[0]);
        $this->assertStringEndsWith('-0002', $numbers[1]);
    }

    #[Test]
    public function checkout_fails_cleanly_when_stock_ran_out_mid_session(): void
    {
        $variant = $this->cartWith(qty: 5, stock: 5);
        $variant->update(['stock_qty' => 0]);

        $this->post(route('checkout.store'), $this->details())
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, Order::count());
    }

    #[Test]
    public function the_confirmation_page_shows_the_order(): void
    {
        $this->cartWith();
        $this->post(route('checkout.store'), $this->details());

        $order = Order::firstOrFail();

        $this->get(route('checkout.confirmation', $order->order_no))
            ->assertOk()
            ->assertSee($order->order_no)
            ->assertSee('T-Shirt');
    }
}
