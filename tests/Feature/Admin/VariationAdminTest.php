<?php

namespace Tests\Feature\Admin;

use App\Enums\VariantStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-002 / REQ-008 */
class VariationAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->product = Product::factory()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'TS-M-BLA',
            'price' => '30.00',
            'stock_qty' => 10,
            'weight_g' => 200,
            'status' => VariantStatus::Active->value,
            'option1_name' => 'Size',
            'option1_value' => 'M',
            'option2_name' => 'Color',
            'option2_value' => 'Black',
        ], $overrides);
    }

    #[Test]
    public function a_price_entered_in_ringgit_is_stored_as_sen(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.products.variations.store', $this->product), $this->payload(['price' => '32.50']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_variants', ['sku' => 'TS-M-BLA', 'price_minor' => 3250]);
    }

    #[Test]
    public function a_duplicate_combination_is_a_field_error_not_a_500(): void
    {
        // The unique index would throw a QueryException; the admin should see a
        // validation message instead.
        $this->actingAs($this->admin)
            ->post(route('admin.products.variations.store', $this->product), $this->payload());

        $this->actingAs($this->admin)
            ->post(route('admin.products.variations.store', $this->product), $this->payload(['sku' => 'OTHER-SKU']))
            ->assertSessionHasErrors('option1_value');

        $this->assertSame(1, $this->product->variants()->count());
    }

    #[Test]
    public function blank_options_are_stored_as_empty_strings_never_null(): void
    {
        $this->actingAs($this->admin)->post(
            route('admin.products.variations.store', $this->product),
            $this->payload([
                'sku' => 'TOTE-STD',
                'option1_name' => '', 'option1_value' => '',
                'option2_name' => '', 'option2_value' => '',
            ])
        )->assertSessionHasNoErrors();

        $variant = $this->product->variants()->firstOrFail();

        $this->assertSame('', $variant->option1_value);
        $this->assertSame('', $variant->option2_value);
        $this->assertNotNull($variant->option1_value);
    }

    #[Test]
    public function an_option_name_without_a_value_is_collapsed(): void
    {
        // A half-filled axis would render a column header with no data.
        $this->actingAs($this->admin)->post(
            route('admin.products.variations.store', $this->product),
            $this->payload([
                'sku' => 'HALF-1',
                'option2_name' => 'Color', 'option2_value' => '',
            ])
        )->assertSessionHasNoErrors();

        $variant = $this->product->variants()->firstOrFail();

        $this->assertSame('', $variant->option2_name);
        $this->assertSame('', $variant->option2_value);
    }

    #[Test]
    public function stock_can_be_adjusted_from_the_variations_screen(): void
    {
        $variant = ProductVariant::factory()->for($this->product)->create(['stock_qty' => 4]);

        $this->actingAs($this->admin)
            ->patch(route('admin.products.variations.stock', [$this->product, $variant]), ['stock_qty' => 25])
            ->assertRedirect();

        $this->assertSame(25, $variant->fresh()->stock_qty);
    }

    #[Test]
    public function negative_stock_is_rejected(): void
    {
        $variant = ProductVariant::factory()->for($this->product)->create(['stock_qty' => 4]);

        $this->actingAs($this->admin)
            ->patch(route('admin.products.variations.stock', [$this->product, $variant]), ['stock_qty' => -1])
            ->assertSessionHasErrors('stock_qty');

        $this->assertSame(4, $variant->fresh()->stock_qty);
    }

    #[Test]
    public function a_variant_belonging_to_another_product_cannot_be_edited_through_the_url(): void
    {
        // Nested route params are two independent ids.
        $other = ProductVariant::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.products.variations.stock', [$this->product, $other]), ['stock_qty' => 999])
            ->assertNotFound();

        $this->assertNotSame(999, $other->fresh()->stock_qty);
    }

    #[Test]
    public function the_same_sku_cannot_be_used_twice_anywhere(): void
    {
        ProductVariant::factory()->create(['sku' => 'TAKEN']);

        $this->actingAs($this->admin)
            ->post(route('admin.products.variations.store', $this->product), $this->payload(['sku' => 'TAKEN']))
            ->assertSessionHasErrors('sku');
    }
}
