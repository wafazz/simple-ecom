<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The variation picker must never leave a buyable variant unreachable.
 *
 * A product whose rows use its option axes inconsistently — one line with the
 * colour left blank beside lines that fill it in — used to render no colour
 * swatches AND hide the <select>, so Add to cart stayed on "Select an option"
 * for good and nothing on the product could be bought.
 */
class PickerShapeTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $rows): Product
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'category_id' => Category::factory()->create(['is_active' => true])->id,
        ]);

        foreach ($rows as $r) {
            ProductVariant::factory()->create([
                'product_id' => $product->id,
                'option1_name' => $r[0], 'option1_value' => $r[1],
                'option2_name' => $r[2], 'option2_value' => $r[3],
                'stock_qty' => $r[4],
            ]);
        }

        return $product;
    }

    /** A row with the colour left blank, beside rows that name it. */
    private function halfFilledAxis(): Product
    {
        return $this->product([
            ['Size', 'S', '', '', 5],
            ['Size', 'S', 'Color', 'Black', 10],
            ['Size', 'S', 'Color', 'White', 8],
        ]);
    }

    #[Test]
    public function a_half_filled_axis_falls_back_to_the_dropdown(): void
    {
        $html = $this->get(route('products.show', $this->halfFilledAxis()))
            ->assertOk()->getContent();

        // No swatch grid, because it could not express the blank row...
        $this->assertStringNotContainsString('data-axis="1"', $html);
        // ...so the control that CAN reach every variant stays visible.
        $this->assertStringNotContainsString('js-hidden">', $html);
    }

    #[Test]
    public function every_variant_of_a_half_filled_product_is_still_listed(): void
    {
        $product = $this->halfFilledAxis();

        $html = $this->get(route('products.show', $product))->assertOk()->getContent();

        foreach ($product->variants as $variant) {
            $this->assertStringContainsString(
                'value="'.$variant->id.'"',
                $html,
                "Variant {$variant->id} is not selectable anywhere on the page.",
            );
        }
    }

    #[Test]
    public function every_variant_of_a_half_filled_product_can_be_bought(): void
    {
        $product = $this->halfFilledAxis();

        foreach ($product->variants as $variant) {
            $this->post(route('cart.store'), ['variant_id' => $variant->id, 'qty' => 1]);

            $this->assertStringContainsString(
                'Added',
                (string) session('status'),
                "Variant {$variant->id} could not be added to the cart.",
            );
        }
    }

    #[Test]
    public function a_consistent_two_axis_product_still_uses_swatches(): void
    {
        $product = $this->product([
            ['Size', 'S', 'Color', 'Black', 10],
            ['Size', 'S', 'Color', 'White', 8],
            ['Size', 'M', 'Color', 'Black', 4],
        ]);

        $html = $this->get(route('products.show', $product))->assertOk()->getContent();

        $this->assertStringContainsString('data-axis="1"', $html);
        $this->assertStringContainsString('data-axis="2"', $html);
        $this->assertStringContainsString('js-hidden">', $html, 'The dropdown is redundant here.');
    }

    #[Test]
    public function the_axis_name_is_found_even_when_the_first_row_omits_it(): void
    {
        // Both rows FILL the colour, but the row sorting first ('Black' before
        // 'White') was saved without a name for the axis. Reading the label off
        // that row alone hid the colour swatches and stranded both variants.
        $html = $this->get(route('products.show', $this->product([
            ['Size', 'S', '', 'Black', 10],
            ['Size', 'S', 'Color', 'White', 8],
        ])))->assertOk()->getContent();

        $this->assertStringContainsString('Color', $html, 'The axis label was not recovered.');
        $this->assertStringContainsString('data-axis="2"', $html, 'Colour swatches must render.');
    }

    #[Test]
    public function a_single_option_product_keeps_its_swatches(): void
    {
        $html = $this->get(route('products.show', $this->product([
            ['Size', 'S', '', '', 5],
            ['Size', 'M', '', '', 3],
        ])))->assertOk()->getContent();

        // Axis 2 is unused by EVERY row here, which is consistent, not partial.
        $this->assertStringContainsString('data-axis="1"', $html);
        $this->assertStringNotContainsString('data-axis="2"', $html);
    }

    #[Test]
    public function a_product_with_no_options_at_all_still_buys(): void
    {
        // The unique index allows exactly one optionless row per product.
        $product = $this->product([['', '', '', '', 5]]);

        $html = $this->get(route('products.show', $product))->assertOk()->getContent();

        $this->assertStringNotContainsString('js-hidden">', $html);

        $this->post(route('cart.store'), [
            'variant_id' => $product->variants->first()->id, 'qty' => 1,
        ]);
        $this->assertStringContainsString('Added', (string) session('status'));
    }
}
