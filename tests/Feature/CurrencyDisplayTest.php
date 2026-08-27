<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The currency symbol must appear exactly once against a price.
 *
 * Money::display() already carries "RM", so a template that ALSO prints the
 * shared $currencySymbol rendered "RMRM5.00". Money::format() is the
 * symbol-free half and is what belongs beside an explicit symbol.
 */
class CurrencyDisplayTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Checked against the VISIBLE text, not the raw markup.
     *
     * "RM<span data-variant-price>RM5.00</span>" reads as RMRM5.00 on screen
     * while containing no "RMRM" in source — searching the HTML alone let the
     * original bug through this very assertion.
     */
    private function assertNoDoubledSymbol(string $html): void
    {
        $symbol = (string) config('shop.currency_symbol');
        $text = preg_replace('/<[^>]*>/', '', $html);

        foreach (['source' => $html, 'rendered text' => $text] as $where => $subject) {
            $this->assertStringNotContainsString(
                $symbol.$symbol,
                (string) $subject,
                "The currency symbol is printed twice ({$symbol}{$symbol}) in the {$where}.",
            );
        }
    }

    private function product(int $priceMinor = 500): Product
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'category_id' => Category::factory()->create(['is_active' => true])->id,
        ]);

        foreach ([['S', 3], ['M', 4]] as $i => [$size, $stock]) {
            ProductVariant::factory()->create([
                'product_id' => $product->id,
                'option1_name' => 'Size', 'option1_value' => $size,
                'price_minor' => $priceMinor + $i, 'stock_qty' => $stock,
            ]);
        }

        return $product;
    }

    #[Test]
    public function the_product_page_prints_one_symbol(): void
    {
        $html = $this->get(route('products.show', $this->product()))
            ->assertOk()->getContent();

        $this->assertNoDoubledSymbol($html);
        $this->assertStringContainsString('RM', $html);
    }

    #[Test]
    public function the_headline_price_is_symbol_free_inside_its_span(): void
    {
        $html = $this->get(route('products.show', $this->product(500)))
            ->assertOk()->getContent();

        // The picker overwrites this span, so the symbol must live outside it.
        $this->assertMatchesRegularExpression(
            '/<span data-variant-price>\s*5\.00\s*<\/span>/',
            $html,
        );
    }

    #[Test]
    public function the_json_handed_to_the_picker_is_symbol_free(): void
    {
        $html = $this->get(route('products.show', $this->product()))
            ->assertOk()->getContent();

        // Swapping a variant must not reintroduce the symbol.
        $this->assertStringNotContainsString('&quot;price&quot;:&quot;RM', $html);
    }

    #[Test]
    public function the_listing_and_home_print_one_symbol(): void
    {
        $this->product();

        $this->assertNoDoubledSymbol($this->get(route('products.index'))->assertOk()->getContent());
        $this->assertNoDoubledSymbol($this->get(route('home'))->assertOk()->getContent());
    }

    #[Test]
    public function the_admin_order_page_prints_one_symbol(): void
    {
        $order = Order::factory()->create([
            'order_status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Paid,
        ]);

        // The courier-charged row is the one that doubled.
        Shipment::factory()->create(['order_id' => $order->id, 'cost_minor' => 1234]);

        $html = $this->actingAs(User::factory()->create())
            ->get(route('admin.orders.show', $order))
            ->assertOk()->getContent();

        $this->assertNoDoubledSymbol($html);
        $this->assertStringContainsString('12.34', $html);
    }
}
