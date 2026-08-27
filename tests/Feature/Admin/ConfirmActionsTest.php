<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Slide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every admin control that changes something asks first.
 *
 * One mechanism throughout: data-confirm on the form, or on the button when it
 * sits outside the form it submits. Nothing here is a security control — the
 * server re-checks all of it and each action still works with JavaScript off.
 * It is there so a mis-aimed click on a row of small buttons does not silently
 * change an order.
 */
class ConfirmActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    private function page(string $route, mixed $param = null): string
    {
        return $this->actingAs($this->admin)
            ->get($param === null ? route($route) : route($route, $param))
            ->assertOk()
            ->getContent();
    }

    #[Test]
    public function every_row_action_on_a_pending_order_asks_first(): void
    {
        $order = Order::factory()->create([
            'order_status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
        ]);

        $html = $this->page('admin.orders.index');

        foreach (['Approve order '.$order->order_no, 'Cancel order '.$order->order_no,
            'Delete order '.$order->order_no] as $prompt) {
            $this->assertStringContainsString($prompt, $html);
        }

        // Approve must say what it does NOT do.
        $this->assertStringContainsString('NOT marked as paid', $html);
    }

    #[Test]
    public function moving_an_order_to_processing_asks_first(): void
    {
        $order = Order::factory()->create([
            'order_status' => OrderStatus::NewOrder,
            'payment_status' => PaymentStatus::Paid,
        ]);

        $this->assertStringContainsString(
            'Move order '.$order->order_no.' to Processing?',
            $this->page('admin.orders.index'),
        );
    }

    #[Test]
    public function the_bulk_action_asks_first(): void
    {
        Order::factory()->create([
            'order_status' => OrderStatus::NewOrder,
            'payment_status' => PaymentStatus::Paid,
        ]);

        // The button sits outside its form, so it carries the attribute itself.
        $this->assertStringContainsString(
            'data-confirm="Move the selected orders to Processing?"',
            $this->page('admin.orders.index'),
        );
    }

    #[Test]
    public function reading_screens_do_not_ask(): void
    {
        Order::factory()->create([
            'order_status' => OrderStatus::NewOrder,
            'payment_status' => PaymentStatus::Paid,
        ]);

        $html = $this->page('admin.orders.index');

        // Print AWB and Book courier only open a screen; nothing has happened
        // yet, and a prompt in front of a page that changes nothing trains the
        // admin to click through prompts.
        $this->assertStringNotContainsString('data-confirm="Print', $html);
        $this->assertStringNotContainsString('data-confirm="Book', $html);
    }

    #[Test]
    public function the_status_and_refund_controls_ask_first(): void
    {
        $order = Order::factory()->create([
            'order_status' => OrderStatus::NewOrder,
            'payment_status' => PaymentStatus::Paid,
        ]);

        $html = $this->page('admin.orders.show', $order);

        $this->assertStringContainsString('Change the status of order '.$order->order_no, $html);
        $this->assertStringContainsString('Mark order '.$order->order_no.' refunded?', $html);
    }

    #[Test]
    public function deactivating_a_product_or_category_asks_first(): void
    {
        $category = Category::factory()->create(['is_active' => true, 'name' => 'Jersey']);
        Product::factory()->create(['is_active' => true, 'name' => 'Home Kit', 'category_id' => $category->id]);

        $this->assertStringContainsString('Deactivate “Home Kit”?', $this->page('admin.products.index'));
        $this->assertStringContainsString('Deactivate the category “Jersey”?', $this->page('admin.categories.index'));
    }

    #[Test]
    public function banner_controls_ask_first(): void
    {
        Slide::create(['headline' => 'Banner', 'focal' => 'center', 'sort_order' => 0, 'is_active' => true]);

        $html = $this->page('admin.slides.index');

        $this->assertStringContainsString('Hide this banner on the shop front?', $html);
        $this->assertStringContainsString('Delete this banner?', $html);
        // Deleting a banner takes the artwork with it, which is not recoverable.
        $this->assertStringContainsString('cannot be recovered', $html);
    }

    #[Test]
    public function the_saved_credential_and_connection_controls_ask_first(): void
    {
        $html = $this->page('admin.integrations.index');

        $this->assertStringContainsString('Switch Toyyibpay to PRODUCTION?', $html);
    }

    #[Test]
    public function editing_forms_are_not_interrupted(): void
    {
        $category = Category::factory()->create();

        // A form you filled in yourself needs no prompt: the intent is the
        // typing. Prompting on Save is friction that teaches people to dismiss
        // prompts without reading them.
        $html = $this->page('admin.categories.edit', $category);

        $this->assertStringNotContainsString('data-confirm', $html);
    }
}
