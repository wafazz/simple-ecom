<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\IntegrationToken;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec §27 Phase 10 — the complete purchase flow, end to end.
 *
 * Everything below the network boundary is real: real routes, real controllers,
 * real database. Only the two third-party APIs are faked.
 */
class PurchaseFlowTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $black;

    private ProductVariant $white;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.toyyibpay.secret_key' => 'tp-secret',
            'services.toyyibpay.category_code' => 'tp-cat',
            'services.toyyibpay.base_url' => 'https://dev.toyyibpay.com',
            'services.toyyibpay.amount_format' => 'decimal',
            'services.easyparcel.client_id' => 'ep-client',
            'services.easyparcel.client_secret' => 'ep-secret',
            'services.easyparcel.base_url' => 'https://api.easyparcel.com/open_api/2026-06',
            'services.easyparcel.oauth_url' => 'https://api.easyparcel.com/oauth',
        ]);

        Setting::put('pickup_postcode', '50000');
        Setting::put('pickup_state', 'MY-14');
        Setting::put('flat_shipping_fee_minor', '1000');

        IntegrationToken::create([
            'provider' => 'easyparcel',
            'access_token' => 'access', 'refresh_token' => 'refresh',
            'expires_at' => now()->addHours(10), 'connected_at' => now(),
        ]);

        $category = Category::factory()->create(['name' => 'Apparel', 'slug' => 'apparel']);
        $product = Product::factory()->create([
            'category_id' => $category->id, 'name' => 'T-Shirt', 'slug' => 't-shirt',
        ]);

        $this->black = ProductVariant::factory()->for($product)->options('M', 'Black')
            ->create(['sku' => 'TS-M-BLA', 'price_minor' => 3000, 'stock_qty' => 20, 'weight_g' => 200]);
        $this->white = ProductVariant::factory()->for($product)->options('M', 'White')
            ->create(['sku' => 'TS-M-WHI', 'price_minor' => 3200, 'stock_qty' => 5, 'weight_g' => 200]);
    }

    #[Test]
    public function a_customer_can_browse_choose_a_variation_pay_and_track_the_order(): void
    {
        // Faked ONCE. Http::fake() merges rather than overrides, so re-faking
        // the same pattern later would leave the first stub winning. The
        // verification stub therefore reads the real order at call time.
        Http::fake([
            '*shipment/quotations' => Http::response(['data' => [['quotations' => [[
                'courier' => ['service_id' => 'SVC-A', 'courier_name' => 'J&T', 'service_name' => 'Standard'],
                'pricing' => ['total_amount' => '10.84', 'currency' => 'MYR'],
            ]]]]], 200),
            '*createBill' => Http::response([['BillCode' => 'BILL-E2E']], 200),
            '*getBillTransactions' => function () {
                $order = Order::firstOrFail();

                return Http::response([[
                    'billpaymentStatus' => '1',
                    'billpaymentAmount' => Money::format($order->grand_total_minor),
                    'billExternalReferenceNo' => $order->order_no,
                    'billpaymentInvoiceNo' => 'INV-E2E',
                ]], 200);
            },
        ]);

        // 1. Browse the catalogue and open the product.
        $this->get(route('products.index'))->assertOk()->assertSee('T-Shirt');
        $this->get(route('products.show', 't-shirt'))
            ->assertOk()
            ->assertSee('RM30.00')
            ->assertSee('RM32.00');

        // 2. Add two distinct variations — they must not merge.
        $this->post(route('cart.store'), ['variant_id' => $this->black->id, 'qty' => 2]);
        $this->post(route('cart.store'), ['variant_id' => $this->white->id, 'qty' => 1]);

        $this->get(route('cart.index'))->assertOk()->assertSee('M / Black')->assertSee('M / White');

        // 3. Quote shipping for the delivery address.
        $this->postJson(route('shipping.quote'), ['postcode' => '11900', 'state' => 'MY-07'])
            ->assertOk()
            ->assertJsonPath('quotes.0.service_id', 'SVC-A');

        // 4. Check out. 2×3000 + 1×3200 = 9200, plus 1084 shipping = 10284.
        $this->post(route('checkout.store'), [
            'customer_name' => 'Aisha Rahman',
            'customer_email' => 'aisha@example.test',
            'customer_phone' => '0123456789',
            'address_line' => '12 Jalan Contoh',
            'city' => 'Georgetown',
            'state' => 'MY-07',
            'postcode' => '11900',
            'country' => 'MY',
            'shipping_service_id' => 'SVC-A',
        ])->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame(9200, $order->subtotal_minor);
        $this->assertSame(1084, $order->shipping_fee_minor);
        $this->assertSame(10284, $order->grand_total_minor);
        $this->assertSame('api', $order->shipping_rate_source);
        $this->assertCount(2, $order->items);

        // Stock is checked, not held.
        $this->assertSame(20, $this->black->fresh()->stock_qty);

        // 5. Off to the gateway.
        $this->get(route('payment.pay', $order->order_no))
            ->assertRedirect('https://dev.toyyibpay.com/BILL-E2E');

        // 6. The gateway confirms. The amount and reference must both match the
        //    stored order or settlement is refused.
        $this->post(route('payment.callback'), ['billcode' => 'BILL-E2E'])->assertOk();

        // 7. Settled: statuses moved and stock decremented exactly once.
        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Processing, $order->order_status);
        $this->assertSame(18, $this->black->fresh()->stock_qty);
        $this->assertSame(4, $this->white->fresh()->stock_qty);
        $this->assertSame('INV-E2E', $order->payment->provider_ref);

        // 8. The customer can track it with the order number and their email.
        $this->post(route('order-status.lookup'), [
            'order_no' => $order->order_no,
            'email' => 'aisha@example.test',
        ])->assertOk()->assertSee($order->order_no)->assertSee('T-Shirt');

        // 9. The admin sees it.
        $this->actingAs(User::factory()->create())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Aisha Rahman')
            ->assertSee('INV-E2E');
    }

    #[Test]
    public function the_whole_flow_still_completes_when_both_third_party_apis_are_down(): void
    {
        // A courier outage must not cost a sale, and a payment gateway outage
        // must not settle anything (Planning §11.A.5, §11.B.6).
        Http::fake([
            '*shipment/quotations' => Http::response('', 500),
            '*createBill' => Http::response('', 500),
        ]);

        $this->post(route('cart.store'), ['variant_id' => $this->black->id, 'qty' => 1]);

        $this->post(route('checkout.store'), [
            'customer_name' => 'Aisha', 'customer_email' => 'a@b.test', 'customer_phone' => '0123456789',
            'address_line' => '12 Jalan', 'city' => 'Georgetown', 'state' => 'MY-07',
            'postcode' => '11900', 'country' => 'MY', 'shipping_service_id' => 'SVC-A',
        ])->assertRedirect();

        $order = Order::firstOrFail();

        // Order placed at the flat rate rather than lost.
        $this->assertSame('flat', $order->shipping_rate_source);
        $this->assertSame(1000, $order->shipping_fee_minor);

        // The bill could not be created, so the customer is told plainly and
        // the order survives for a retry — nothing is marked paid.
        $this->get(route('payment.pay', $order->order_no))
            ->assertRedirect(route('checkout.confirmation', $order->order_no))
            ->assertSessionHas('error');

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
        $this->assertSame(20, $this->black->fresh()->stock_qty);
    }
}
