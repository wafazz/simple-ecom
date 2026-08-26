<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-007 — Planning §14, unauthorized order access. */
class OrderStatusTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_lookup_form_renders(): void
    {
        $this->get(route('order-status.show'))
            ->assertOk()
            ->assertSee('Track Your Order');
    }

    #[Test]
    public function an_order_is_returned_when_number_and_email_both_match(): void
    {
        $order = Order::factory()->create([
            'order_no' => 'ORD-20260826-0001',
            'customer_email' => 'buyer@example.test',
        ]);
        OrderItem::factory()->for($order)->create(['product_name' => 'T-Shirt']);

        $this->post(route('order-status.lookup'), [
            'order_no' => 'ORD-20260826-0001',
            'email' => 'buyer@example.test',
        ])->assertOk()->assertSee('ORD-20260826-0001')->assertSee('T-Shirt');
    }

    #[Test]
    public function a_correct_order_number_with_the_wrong_email_reveals_nothing(): void
    {
        // Order numbers are sequential and guessable. The email is the
        // authorisation check, not decoration.
        Order::factory()->create([
            'order_no' => 'ORD-20260826-0002',
            'customer_email' => 'buyer@example.test',
            'customer_name' => 'Aisha Binti Rahman',
        ]);

        $response = $this->post(route('order-status.lookup'), [
            'order_no' => 'ORD-20260826-0002',
            'email' => 'attacker@example.test',
        ]);

        $response->assertOk();
        $response->assertDontSee('Aisha Binti Rahman');
        $response->assertSee('could not find an order');
    }

    #[Test]
    public function a_wrong_email_and_a_missing_order_give_the_same_answer(): void
    {
        // Distinguishing the two would be an enumeration oracle.
        Order::factory()->create([
            'order_no' => 'ORD-20260826-0003',
            'customer_email' => 'buyer@example.test',
        ]);

        $wrongEmail = $this->post(route('order-status.lookup'), [
            'order_no' => 'ORD-20260826-0003', 'email' => 'attacker@example.test',
        ])->getContent();

        $noSuchOrder = $this->post(route('order-status.lookup'), [
            'order_no' => 'ORD-99999999-9999', 'email' => 'attacker@example.test',
        ])->getContent();

        $this->assertSame($wrongEmail, $noSuchOrder);
    }

    #[Test]
    public function the_lookup_validates_its_input(): void
    {
        $this->post(route('order-status.lookup'), ['order_no' => '', 'email' => 'not-an-email'])
            ->assertSessionHasErrors(['order_no', 'email']);
    }

    #[Test]
    public function the_lookup_form_carries_a_csrf_token(): void
    {
        // Laravel skips CSRF verification while running tests, so asserting a
        // 419 here would only prove the framework's test shim works. What is
        // worth guarding is that the form actually ships a token.
        $this->get(route('order-status.show'))
            ->assertOk()
            ->assertSee('name="_token"', false);
    }
}
