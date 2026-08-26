<?php

namespace Tests\Feature;

use App\Models\IntegrationToken;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\EasyParcelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-006 — Planning §11.B. No live calls: Http::fake() throughout. */
class ShippingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.easyparcel.client_id' => 'test-client',
            'services.easyparcel.client_secret' => 'test-secret',
            'services.easyparcel.base_url' => 'https://api.easyparcel.com/open_api/2026-06',
            'services.easyparcel.oauth_url' => 'https://api.easyparcel.com/oauth',
        ]);

        Setting::put('pickup_postcode', '50000');
        Setting::put('pickup_state', 'MY-14');
        Setting::put('flat_shipping_fee_minor', '1000');
    }

    private function connect(bool $expired = false): IntegrationToken
    {
        return IntegrationToken::create([
            'provider' => 'easyparcel',
            'access_token' => 'live-access-token',
            'refresh_token' => 'live-refresh-token',
            'expires_at' => $expired ? now()->subMinute() : now()->addHours(10),
            'connected_at' => now(),
        ]);
    }

    private function quotationBody(): array
    {
        return ['data' => [[
            'quotations' => [
                [
                    'courier' => ['service_id' => 'SVC-B', 'courier_name' => 'Pos Laju', 'service_name' => 'Next Day'],
                    'pricing' => ['total_amount' => '14.20', 'currency' => 'MYR'],
                    'delivery_duration' => null,
                ],
                [
                    'courier' => ['service_id' => 'SVC-A', 'courier_name' => 'J&T', 'service_name' => 'Standard'],
                    'pricing' => ['total_amount' => '10.84', 'currency' => 'MYR'],
                ],
            ],
        ]]];
    }

    private function cartWithOneItem(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)
            ->create(['price_minor' => 3000, 'stock_qty' => 10, 'weight_g' => 400]);

        $this->post(route('cart.store'), ['variant_id' => $variant->id, 'qty' => 1]);
    }

    #[Test]
    public function a_decimal_string_amount_is_converted_to_sen_once(): void
    {
        // total_amount is a DECIMAL STRING, not minor units (Planning §11.B.1).
        $this->connect();
        Http::fake(['*shipment/quotations' => Http::response($this->quotationBody(), 200)]);

        $quotes = app(EasyParcelService::class)->quote('11900', 'MY-07', 400);

        $this->assertSame(1084, $quotes[0]->priceMinor);
        $this->assertSame(1420, $quotes[1]->priceMinor);
        $this->assertIsInt($quotes[0]->priceMinor);
    }

    #[Test]
    public function quotes_are_returned_cheapest_first(): void
    {
        $this->connect();
        Http::fake(['*shipment/quotations' => Http::response($this->quotationBody(), 200)]);

        $quotes = app(EasyParcelService::class)->quote('11900', 'MY-07', 400);

        $this->assertSame('SVC-A', $quotes[0]->serviceId);
        $this->assertSame('J&T — Standard', $quotes[0]->label());
    }

    #[Test]
    public function the_request_uses_iso_subdivision_codes(): void
    {
        $this->connect();
        Http::fake(['*shipment/quotations' => Http::response($this->quotationBody(), 200)]);

        app(EasyParcelService::class)->quote('11900', 'MY-07', 400);

        Http::assertSent(function ($request) {
            $shipment = $request['shipment'][0];

            return $shipment['sender']['subdivision_code'] === 'MY-14'
                && $shipment['receiver']['subdivision_code'] === 'MY-07'
                && $shipment['receiver']['postcode'] === '11900';
        });
    }

    #[Test]
    public function an_api_failure_falls_back_to_the_flat_rate(): void
    {
        $this->connect();
        Http::fake(['*shipment/quotations' => Http::response('', 500)]);

        $quotes = app(EasyParcelService::class)->quote('11900', 'MY-07', 400);

        $this->assertCount(1, $quotes);
        $this->assertTrue($quotes[0]->isFlat());
        $this->assertSame(1000, $quotes[0]->priceMinor);
    }

    #[Test]
    public function an_unparseable_body_falls_back_to_the_flat_rate(): void
    {
        $this->connect();
        Http::fake(['*shipment/quotations' => Http::response('<html>error</html>', 200)]);

        $this->assertTrue(app(EasyParcelService::class)->quote('11900', 'MY-07', 400)[0]->isFlat());
    }

    #[Test]
    public function being_disconnected_falls_back_to_the_flat_rate(): void
    {
        Http::fake();

        $quotes = app(EasyParcelService::class)->quote('11900', 'MY-07', 400);

        $this->assertTrue($quotes[0]->isFlat());
        Http::assertNothingSent();
    }

    #[Test]
    public function an_expired_access_token_is_refreshed_and_the_new_refresh_token_is_persisted(): void
    {
        // The refresh token ROTATES. Keeping the old one kills the integration
        // silently at the next refresh (Planning §11.B.3).
        $this->connect(expired: true);

        Http::fake([
            '*oauth/token' => Http::response([
                'access_token' => 'new-access',
                'refresh_token' => 'rotated-refresh',
                'expires_in' => 36000,
            ], 200),
            '*shipment/quotations' => Http::response($this->quotationBody(), 200),
        ]);

        $quotes = app(EasyParcelService::class)->quote('11900', 'MY-07', 400);

        $token = IntegrationToken::firstOrFail();
        $this->assertSame('new-access', $token->access_token);
        $this->assertSame('rotated-refresh', $token->refresh_token);
        $this->assertFalse($quotes[0]->isFlat());
    }

    #[Test]
    public function a_failed_refresh_falls_back_rather_than_erroring(): void
    {
        $this->connect(expired: true);

        Http::fake([
            '*oauth/token' => Http::response('', 401),
            '*shipment/quotations' => Http::response($this->quotationBody(), 200),
        ]);

        $this->assertTrue(app(EasyParcelService::class)->quote('11900', 'MY-07', 400)[0]->isFlat());
    }

    #[Test]
    public function the_quote_endpoint_returns_options_for_the_current_cart(): void
    {
        $this->connect();
        Http::fake(['*shipment/quotations' => Http::response($this->quotationBody(), 200)]);
        $this->cartWithOneItem();

        $this->postJson(route('shipping.quote'), ['postcode' => '11900', 'state' => 'MY-07'])
            ->assertOk()
            ->assertJsonPath('quotes.0.service_id', 'SVC-A')
            ->assertJsonPath('quotes.0.price', 'RM10.84');
    }

    #[Test]
    public function the_quote_endpoint_rejects_a_free_text_state(): void
    {
        $this->cartWithOneItem();

        $this->postJson(route('shipping.quote'), ['postcode' => '11900', 'state' => 'Penang'])
            ->assertStatus(422);
    }

    #[Test]
    public function the_chosen_courier_is_re_priced_server_side_at_order_creation(): void
    {
        // The browser posts an identifier, never a price.
        $this->connect();
        Http::fake(['*shipment/quotations' => Http::response($this->quotationBody(), 200)]);
        $this->cartWithOneItem();

        $this->post(route('checkout.store'), [
            'customer_name' => 'Aisha', 'customer_email' => 'a@b.test', 'customer_phone' => '0123456789',
            'address_line' => '12 Jalan', 'city' => 'Georgetown', 'state' => 'MY-07',
            'postcode' => '11900', 'country' => 'MY',
            'shipping_service_id' => 'SVC-B',
            'shipping_fee_minor' => 1,
        ]);

        $order = Order::firstOrFail();

        $this->assertSame(1420, $order->shipping_fee_minor);
        $this->assertSame('SVC-B', $order->courier_service_id);
        $this->assertSame('api', $order->shipping_rate_source);
        $this->assertSame(3000 + 1420, $order->grand_total_minor);
    }

    #[Test]
    public function a_courier_that_is_no_longer_quoted_falls_back_to_the_flat_rate(): void
    {
        $this->connect();
        Http::fake(['*shipment/quotations' => Http::response($this->quotationBody(), 200)]);
        $this->cartWithOneItem();

        $this->post(route('checkout.store'), [
            'customer_name' => 'Aisha', 'customer_email' => 'a@b.test', 'customer_phone' => '0123456789',
            'address_line' => '12 Jalan', 'city' => 'Georgetown', 'state' => 'MY-07',
            'postcode' => '11900', 'country' => 'MY',
            'shipping_service_id' => 'SVC-GONE',
        ]);

        $order = Order::firstOrFail();

        $this->assertSame(1000, $order->shipping_fee_minor);
        $this->assertSame('flat', $order->shipping_rate_source);
    }
}
