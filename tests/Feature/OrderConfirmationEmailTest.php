<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Mail\OrderPaid;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Support\IntegrationConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The buyer is told once payment is VERIFIED — not when the order is placed.
 *
 * Three things carry the weight. It goes out exactly once, because ToyyibPay
 * sends both a return and a callback for the same payment. It is silent when no
 * mail transport is set up. And it can never break a settlement: the money has
 * arrived, and an email failure that reached the gateway would have it retry a
 * callback that already succeeded.
 */
class OrderConfirmationEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        config([
            'services.toyyibpay.secret_key' => 'test-secret',
            'services.toyyibpay.category_code' => 'test-category',
            'services.toyyibpay.base_url' => 'https://dev.toyyibpay.com',
            'services.toyyibpay.amount_format' => 'decimal',
        ]);
    }

    private function configureMail(): void
    {
        Setting::put('mail_smtp_host', 'smtp.example.com');
        IntegrationConfig::put('mail.smtp_username', 'hello@example.com');
        IntegrationConfig::put('mail.smtp_password', 'secret');
        Setting::put('mail_from_address', 'hello@example.com');
    }

    private function order(): Order
    {
        $variant = ProductVariant::factory()->create(['price_minor' => 3000, 'stock_qty' => 10]);

        $order = Order::factory()->create([
            'order_no' => 'ORD-20260828-0001',
            'customer_email' => 'buyer@example.com',
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

        Payment::factory()->for($order)->create(['bill_code' => 'BILL123', 'amount_minor' => 7000]);

        return $order;
    }

    private function settle(): void
    {
        Http::fake(['*getBillTransactions' => Http::response([[
            'billpaymentStatus' => '1',
            'billpaymentAmount' => '70.00',
            'billExternalReferenceNo' => 'ORD-20260828-0001',
            'billpaymentInvoiceNo' => 'INV-999',
        ]], 200)]);

        $this->post(route('payment.callback'), ['billcode' => 'BILL123'])->assertOk();
    }

    // -------------------------------------------------------------- sending

    #[Test]
    public function a_verified_payment_emails_the_buyer(): void
    {
        $this->configureMail();
        $order = $this->order();

        $this->settle();

        Mail::assertSent(OrderPaid::class, fn (OrderPaid $mail): bool => $mail->hasTo('buyer@example.com')
            && $mail->order->is($order));
    }

    #[Test]
    public function the_subject_carries_the_order_number(): void
    {
        $this->configureMail();
        $this->order();

        $this->settle();

        Mail::assertSent(OrderPaid::class, function (OrderPaid $mail): bool {
            // What the customer quotes back, and searches their inbox for.
            return $mail->envelope()->subject === 'Order ORD-20260828-0001 confirmed';
        });
    }

    #[Test]
    public function a_duplicate_callback_does_not_email_twice(): void
    {
        $this->configureMail();
        $this->order();

        Http::fake(['*getBillTransactions' => Http::response([[
            'billpaymentStatus' => '1',
            'billpaymentAmount' => '70.00',
            'billExternalReferenceNo' => 'ORD-20260828-0001',
            'billpaymentInvoiceNo' => 'INV-999',
        ]], 200)]);

        // ToyyibPay sends a return AND a callback for the same payment.
        $this->post(route('payment.callback'), ['billcode' => 'BILL123'])->assertOk();
        $this->post(route('payment.callback'), ['billcode' => 'BILL123'])->assertOk();
        $this->post(route('payment.callback'), ['billcode' => 'BILL123'])->assertOk();

        Mail::assertSent(OrderPaid::class, 1);
    }

    // --------------------------------------------------------------- silent

    #[Test]
    public function nothing_is_sent_while_mail_is_not_configured(): void
    {
        $this->order();

        $this->settle();

        Mail::assertNothingSent();
    }

    #[Test]
    public function an_unconfigured_shop_still_settles_the_payment(): void
    {
        $order = $this->order();

        $this->settle();

        // The email is the optional part. The money is not.
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
        $this->assertSame(8, $order->items->first()->variant->fresh()->stock_qty);
    }

    #[Test]
    public function half_configured_mail_counts_as_not_configured(): void
    {
        // A username with no password cannot send; attempting it on every
        // payment would raise on every payment.
        Setting::put('mail_smtp_host', 'smtp.example.com');
        IntegrationConfig::put('mail.smtp_username', 'hello@example.com');
        Setting::put('mail_from_address', 'hello@example.com');

        $this->order();
        $this->settle();

        Mail::assertNothingSent();
    }

    // ------------------------------------------------------------- failures

    #[Test]
    public function a_failing_email_never_breaks_the_settlement(): void
    {
        $this->configureMail();
        $order = $this->order();

        // Whatever goes wrong in the transport, the gateway must still get its
        // 200 — otherwise it retries a callback that already took the money.
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp is down'));

        $this->settle();

        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
        $this->assertSame(8, $order->items->first()->variant->fresh()->stock_qty);
    }

    #[Test]
    public function an_unpaid_order_gets_no_confirmation(): void
    {
        $this->configureMail();
        $this->order();

        Http::fake(['*getBillTransactions' => Http::response([[
            'billpaymentStatus' => '3',
            'billpaymentAmount' => '70.00',
            'billExternalReferenceNo' => 'ORD-20260828-0001',
        ]], 200)]);

        $this->post(route('payment.callback'), ['billcode' => 'BILL123'])->assertOk();

        Mail::assertNothingSent();
    }

    // ------------------------------------------------------------- contents

    #[Test]
    public function the_message_renders_with_the_order_on_it(): void
    {
        $this->configureMail();
        $order = $this->order();

        $html = (new OrderPaid($order->fresh(['items'])))->render();

        $this->assertStringContainsString('ORD-20260828-0001', $html);
        $this->assertStringContainsString('70.00', $html);
        $this->assertStringContainsString($order->customer_name, $html);
    }

    #[Test]
    public function a_printed_nameset_is_spelled_out_for_the_buyer_to_check(): void
    {
        $order = $this->order();
        $order->items()->first()->update([
            'nameset_name' => 'AZLAN',
            'nameset_number' => '10',
            'nameset_price_minor' => 1500,
        ]);

        $html = (new OrderPaid($order->fresh(['items'])))->render();

        // Made to order and not returnable, so the buyer needs to see it while
        // there is still time to say something.
        $this->assertStringContainsString('AZLAN 10', $html);
        $this->assertStringContainsString('check the spelling', $html);
    }
}
