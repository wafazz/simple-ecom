<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A settled order is POSTed to an endpoint on another host.
 *
 * This VPS blocks outbound SMTP, so anything that has to leave goes out from
 * somewhere else. What matters here is that it goes exactly ONCE — ToyyibPay
 * sends both a return and a callback for one payment — that it carries every
 * figure the far end has to print, and that it can never break a settlement:
 * the money has arrived, and a failure reaching the gateway would have it
 * retry a callback that already succeeded.
 */
class OrderRelayTest extends TestCase
{
    use RefreshDatabase;

    private const RELAY = 'https://relay.test/send-email.php';

    protected function setUp(): void
    {
        parent::setUp();

        // The SMTP path is not what is under test and cannot work on this host
        // anyway; faked so it stays silent either way.
        Mail::fake();

        config([
            'services.toyyibpay.secret_key' => 'test-secret',
            'services.toyyibpay.category_code' => 'test-category',
            'services.toyyibpay.base_url' => 'https://dev.toyyibpay.com',
            'services.toyyibpay.amount_format' => 'decimal',
            'services.order_relay.url' => self::RELAY,
            'services.order_relay.token' => 'shared-secret',
        ]);
    }

    private function order(): Order
    {
        $variant = ProductVariant::factory()->create(['price_minor' => 3000, 'stock_qty' => 10]);

        $order = Order::factory()->create([
            'order_no' => 'ORD-20260828-0001',
            'customer_name' => 'Ahmad Faiz',
            'customer_email' => 'buyer@example.com',
            'subtotal_minor' => 6000,
            'shipping_fee_minor' => 1000,
            'grand_total_minor' => 7000,
        ]);

        OrderItem::factory()->for($order)->create([
            'product_variant_id' => $variant->id,
            'product_name' => 'Home Kit 2026/27',
            'variation_label' => 'M',
            'unit_price_minor' => 3000,
            'qty' => 2,
            'line_total_minor' => 6000,
        ]);

        Payment::factory()->for($order)->create(['bill_code' => 'BILL123', 'amount_minor' => 7000]);

        return $order;
    }

    /** @param  array<string, mixed>|null  $relayResponse */
    private function settle(?array $relayResponse = null, int $relayStatus = 200): void
    {
        Http::fake([
            '*getBillTransactions' => Http::response([[
                'billpaymentStatus' => '1',
                'billpaymentAmount' => '70.00',
                'billExternalReferenceNo' => 'ORD-20260828-0001',
                'billpaymentInvoiceNo' => 'INV-999',
            ]], 200),
            self::RELAY => Http::response($relayResponse ?? ['success' => true], $relayStatus),
        ]);

        $this->post(route('payment.callback'), ['billcode' => 'BILL123'])->assertOk();
    }

    /** @return array<string, mixed> */
    private function sentPayload(): array
    {
        $payload = [];

        Http::assertSent(function (Request $request) use (&$payload): bool {
            if ($request->url() !== self::RELAY) {
                return false;
            }

            $payload = $request->data();

            return true;
        });

        return $payload;
    }

    // ------------------------------------------------------------- the post

    #[Test]
    public function a_settled_order_is_posted_to_the_endpoint(): void
    {
        $this->order();
        $this->settle();

        Http::assertSent(fn (Request $r): bool => $r->url() === self::RELAY && $r->method() === 'POST');
    }

    #[Test]
    public function the_payload_carries_every_requested_figure(): void
    {
        $this->order();
        $this->settle();

        $payload = $this->sentPayload();

        $this->assertSame('Ahmad Faiz', $payload['customer_name']);
        $this->assertSame('ORD-20260828-0001', $payload['order_no']);
        $this->assertNotEmpty($payload['purchase_date']);

        // Amounts cross as decimal strings, already rounded. The receiving
        // script knows nothing about minor units and does no arithmetic.
        $this->assertSame('60.00', $payload['total']);
        $this->assertSame('10.00', $payload['delivery_cost']);
        $this->assertSame('70.00', $payload['grand_total']);

        $this->assertCount(1, $payload['items']);
        $this->assertSame('Home Kit 2026/27', $payload['items'][0]['name']);
        $this->assertSame('M', $payload['items'][0]['variation']);
        $this->assertSame(2, $payload['items'][0]['qty']);
        $this->assertSame('60.00', $payload['items'][0]['total']);

        // Not on the requested list, but the far end has to have somewhere to
        // send it.
        $this->assertSame('buyer@example.com', $payload['customer_email']);
    }

    #[Test]
    public function a_printed_nameset_is_spelled_out_for_the_buyer_to_check(): void
    {
        $order = $this->order();

        OrderItem::factory()->for($order)->create([
            'product_name' => 'Away Kit 2026/27',
            'variation_label' => 'L',
            'nameset_name' => 'AZLAN',
            'nameset_number' => '10',
            'unit_price_minor' => 3000,
            'qty' => 1,
            'line_total_minor' => 3000,
        ]);

        $this->settle();

        $namesets = array_column($this->sentPayload()['items'], 'nameset');

        // Made to order, so a misspelling has to be visible while it is still
        // worth reporting. The plain line carries null, not an empty string.
        $this->assertContains('AZLAN 10', $namesets);
        $this->assertContains(null, $namesets);
    }

    #[Test]
    public function the_token_travels_in_the_header_and_never_the_url(): void
    {
        $this->order();
        $this->settle();

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== self::RELAY) {
                return false;
            }

            // A query string is written to the receiving host's access log and
            // to every proxy in between, and this is a credential.
            $this->assertStringNotContainsString('shared-secret', $request->url());

            return $request->hasHeader('X-Relay-Token', 'shared-secret');
        });
    }

    // ------------------------------------------------------------ exactly once

    #[Test]
    public function a_duplicate_callback_does_not_post_twice(): void
    {
        $this->order();
        $this->settle();

        // ToyyibPay sends both a return and a callback for one payment. Only
        // the caller that actually settled the order may post it.
        $this->post(route('payment.callback'), ['billcode' => 'BILL123'])->assertOk();

        Http::assertSentCount(3); // two gateway verifications, one relay post
        $this->assertSame(1, $this->relayPostCount());
    }

    #[Test]
    public function an_unpaid_order_is_not_posted(): void
    {
        $this->order();

        Http::fake([
            '*getBillTransactions' => Http::response([[
                'billpaymentStatus' => '3', // failed
                'billpaymentAmount' => '70.00',
                'billExternalReferenceNo' => 'ORD-20260828-0001',
            ]], 200),
            self::RELAY => Http::response(['success' => true], 200),
        ]);

        $this->post(route('payment.callback'), ['billcode' => 'BILL123'])->assertOk();

        $this->assertSame(0, $this->relayPostCount());
    }

    // ------------------------------------------------------------- failure

    #[Test]
    public function nothing_is_posted_when_no_endpoint_is_configured(): void
    {
        config(['services.order_relay.url' => null]);

        $this->order();
        $this->settle();

        $this->assertSame(0, $this->relayPostCount());
    }

    #[Test]
    public function an_endpoint_that_refuses_never_breaks_the_settlement(): void
    {
        $order = $this->order();

        $this->settle(['success' => false, 'message' => 'Email failed.'], 500);

        // The money arrived. Whatever the far end did with the payload, the
        // order is paid and the gateway must not be told otherwise.
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    #[Test]
    public function a_200_that_reports_failure_is_not_treated_as_accepted(): void
    {
        $order = $this->order();

        // The endpoint answers 200 with success:false for anything it
        // validated and refused, so the status code alone proves nothing.
        $this->settle(['success' => false, 'message' => 'Unauthorized.'], 200);

        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    private function relayPostCount(): int
    {
        $count = 0;

        Http::recorded(function (Request $request) use (&$count): bool {
            if ($request->url() === self::RELAY) {
                $count++;
            }

            return false;
        });

        return $count;
    }
}
