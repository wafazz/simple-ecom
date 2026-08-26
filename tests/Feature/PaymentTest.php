<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-005 — Planning §11.A. No live calls: Http::fake() throughout. */
class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.toyyibpay.secret_key' => 'test-secret',
            'services.toyyibpay.category_code' => 'test-category',
            'services.toyyibpay.base_url' => 'https://dev.toyyibpay.com',
            'services.toyyibpay.amount_format' => 'decimal',
        ]);
    }

    /** An order of exactly RM70.00 with one line of 2 × RM30 plus RM10 shipping. */
    private function order(int $stock = 10): Order
    {
        $variant = ProductVariant::factory()->create(['price_minor' => 3000, 'stock_qty' => $stock]);

        $order = Order::factory()->create([
            'order_no' => 'ORD-20260826-0001',
            'subtotal_minor' => 6000,
            'shipping_fee_minor' => 1000,
            'grand_total_minor' => 7000,
        ]);

        OrderItem::factory()->for($order)->create([
            'product_variant_id' => $variant->id,
            'unit_price_minor' => 3000,
            'qty' => 2,
            'line_total_minor' => 6000,
        ]);

        Payment::factory()->for($order)->create([
            'bill_code' => 'BILL123',
            'provider_ref' => null,
            'amount_minor' => 7000,
        ]);

        return $order;
    }

    private function fakeVerification(array $row): void
    {
        Http::fake([
            '*getBillTransactions' => Http::response([$row], 200),
        ]);
    }

    private function paidRow(array $overrides = []): array
    {
        return array_merge([
            'billpaymentStatus' => '1',
            'billpaymentAmount' => '70.00',
            'billExternalReferenceNo' => 'ORD-20260826-0001',
            'billpaymentInvoiceNo' => 'INV-999',
        ], $overrides);
    }

    #[Test]
    public function a_verified_payment_settles_the_order_and_decrements_stock_once(): void
    {
        $order = $this->order(stock: 10);
        $this->fakeVerification($this->paidRow());

        $this->post(route('payment.callback'), ['billcode' => 'BILL123'])->assertOk();

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Processing, $order->order_status);
        $this->assertSame(8, ProductVariant::first()->stock_qty);
        $this->assertSame('INV-999', $order->payment->provider_ref);
    }

    #[Test]
    public function a_duplicate_callback_decrements_stock_exactly_once(): void
    {
        // Spec §17 — the gateway may deliver the same notification twice.
        $order = $this->order(stock: 10);
        $this->fakeVerification($this->paidRow());

        $this->post(route('payment.callback'), ['billcode' => 'BILL123'])->assertOk();
        $this->post(route('payment.callback'), ['billcode' => 'BILL123'])->assertOk();
        $this->post(route('payment.callback'), ['billcode' => 'BILL123'])->assertOk();

        $this->assertSame(8, ProductVariant::first()->stock_qty);
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    #[Test]
    public function a_forged_callback_claiming_success_does_not_settle_the_order(): void
    {
        // The callback body is never trusted; the gateway is re-queried and
        // says the bill is still pending.
        $order = $this->order();
        $this->fakeVerification(['billpaymentStatus' => '2']);

        $this->post(route('payment.callback'), [
            'billcode' => 'BILL123',
            'status' => '1',
            'amount' => '70.00',
        ])->assertOk();

        $order->refresh();
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame(10, ProductVariant::first()->stock_qty);
    }

    #[Test]
    public function an_amount_mismatch_refuses_to_settle(): void
    {
        $order = $this->order();
        $this->fakeVerification($this->paidRow(['billpaymentAmount' => '10.00']));

        $this->post(route('payment.callback'), ['billcode' => 'BILL123'])->assertOk();

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
        $this->assertSame(10, ProductVariant::first()->stock_qty);
    }

    #[Test]
    public function a_reference_mismatch_refuses_to_settle(): void
    {
        $order = $this->order();
        $this->fakeVerification($this->paidRow(['billExternalReferenceNo' => 'ORD-SOMEONE-ELSE']));

        $this->post(route('payment.callback'), ['billcode' => 'BILL123'])->assertOk();

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
    }

    #[Test]
    public function an_unrecognised_response_shape_leaves_the_order_pending(): void
    {
        // The OQ-11 case. This is the behaviour that must NOT be "fixed" by
        // guessing a field name.
        $order = $this->order();
        $this->fakeVerification(['someUnknownField' => 'paid', 'total' => '70.00']);

        $this->post(route('payment.callback'), ['billcode' => 'BILL123'])->assertOk();

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
        $this->assertSame(10, ProductVariant::first()->stock_qty);
    }

    #[Test]
    public function an_html_error_page_from_the_gateway_leaves_the_order_pending(): void
    {
        $order = $this->order();
        Http::fake(['*getBillTransactions' => Http::response('<html>502 Bad Gateway</html>', 200)]);

        $this->post(route('payment.callback'), ['billcode' => 'BILL123'])->assertOk();

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
    }

    #[Test]
    public function a_gateway_outage_leaves_the_order_pending(): void
    {
        $order = $this->order();
        Http::fake(['*getBillTransactions' => Http::response('', 500)]);

        $this->post(route('payment.callback'), ['billcode' => 'BILL123'])->assertOk();

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
    }

    #[Test]
    public function a_failed_payment_is_recorded_as_failed(): void
    {
        $order = $this->order();
        $this->fakeVerification(['billpaymentStatus' => '3', 'reason' => 'Insufficient funds']);

        $this->post(route('payment.callback'), ['billcode' => 'BILL123'])->assertOk();

        $this->assertSame(PaymentStatus::Failed, $order->fresh()->payment_status);
        $this->assertSame(10, ProductVariant::first()->stock_qty);
    }

    #[Test]
    public function paying_for_the_last_unit_twice_flags_the_second_order_for_review(): void
    {
        // Planning §7.5: money was taken but stock cannot satisfy the line.
        // It must never be silently accepted.
        $variant = ProductVariant::factory()->create(['price_minor' => 7000, 'stock_qty' => 0]);
        $order = Order::factory()->create([
            'order_no' => 'ORD-20260826-0002',
            'subtotal_minor' => 7000, 'shipping_fee_minor' => 0, 'grand_total_minor' => 7000,
        ]);
        OrderItem::factory()->for($order)->create([
            'product_variant_id' => $variant->id,
            'unit_price_minor' => 7000, 'qty' => 1, 'line_total_minor' => 7000,
        ]);
        Payment::factory()->for($order)->create(['bill_code' => 'BILL999', 'provider_ref' => null]);

        $this->fakeVerification($this->paidRow(['billExternalReferenceNo' => 'ORD-20260826-0002']));

        $this->post(route('payment.callback'), ['billcode' => 'BILL999'])->assertOk();

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::NeedsReview, $order->order_status);
        $this->assertSame(0, $variant->fresh()->stock_qty);
    }

    #[Test]
    public function a_callback_for_an_unknown_bill_code_is_answered_200(): void
    {
        // Answering non-200 would make the gateway retry a message we cannot act on.
        $this->post(route('payment.callback'), ['billcode' => 'NOPE'])->assertOk();
    }

    #[Test]
    public function the_browser_return_url_also_verifies_server_side(): void
    {
        $order = $this->order();
        $this->fakeVerification($this->paidRow());

        $this->get(route('payment.return', ['billcode' => 'BILL123', 'status_id' => '1']))
            ->assertOk()
            ->assertSee('Payment received');

        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    #[Test]
    public function the_return_url_claiming_success_is_not_believed_on_its_own(): void
    {
        $order = $this->order();
        $this->fakeVerification(['billpaymentStatus' => '2']);

        $this->get(route('payment.return', ['billcode' => 'BILL123', 'status_id' => '1']))
            ->assertOk()
            ->assertSee('still being confirmed');

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
    }

    #[Test]
    public function creating_a_bill_sends_the_amount_in_cents_and_the_order_number_as_reference(): void
    {
        $order = $this->order();
        $order->payment()->delete();

        Http::fake(['*createBill' => Http::response([['BillCode' => 'NEWBILL']], 200)]);

        $this->get(route('payment.pay', $order->order_no))
            ->assertRedirect('https://dev.toyyibpay.com/NEWBILL');

        Http::assertSent(function ($request) {
            return $request['billAmount'] === 7000
                && $request['billExternalReferenceNo'] === 'ORD-20260826-0001'
                && strlen($request['billName']) <= 30
                && strlen($request['billDescription']) <= 100;
        });

        $this->assertSame('NEWBILL', $order->fresh()->payment->bill_code);
    }

    #[Test]
    public function an_already_paid_order_cannot_be_paid_again(): void
    {
        $order = $this->order();
        $order->forceFill(['payment_status' => PaymentStatus::Paid])->save();

        $this->get(route('payment.pay', $order->order_no))
            ->assertRedirect(route('checkout.confirmation', $order->order_no));

        Http::assertNothingSent();
    }
}
