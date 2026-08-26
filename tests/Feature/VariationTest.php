<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-002 — Planning §7.1. */
class VariationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function duplicate_option_combinations_are_rejected_by_the_database(): void
    {
        $product = Product::factory()->create();

        ProductVariant::factory()->for($product)->options('M', 'Black')->create();

        $this->expectException(QueryException::class);

        ProductVariant::factory()->for($product)->options('M', 'Black')->create();
    }

    #[Test]
    public function the_same_combination_on_a_different_product_is_allowed(): void
    {
        ProductVariant::factory()->options('M', 'Black')->create();
        ProductVariant::factory()->options('M', 'Black')->create();

        $this->assertSame(2, ProductVariant::count());
    }

    #[Test]
    public function a_product_cannot_have_two_option_less_variants(): void
    {
        // The NULL trap: if the option columns were nullable, MySQL would treat
        // each NULL as distinct and BOTH of these would be allowed.
        $product = Product::factory()->create();

        ProductVariant::factory()->for($product)->create();

        $this->expectException(QueryException::class);

        ProductVariant::factory()->for($product)->create();
    }

    #[Test]
    public function unused_option_slots_are_empty_strings_never_null(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->assertSame('', $variant->fresh()->option1_value);
        $this->assertSame('', $variant->fresh()->option2_value);
        $this->assertNotNull($variant->fresh()->option2_value);
    }

    #[Test]
    public function variation_label_skips_unused_axes(): void
    {
        $both = ProductVariant::factory()->options('M', 'Black')->create();
        $one = ProductVariant::factory()->options('L')->create();
        $none = ProductVariant::factory()->create();

        $this->assertSame('M / Black', $both->variationLabel());
        $this->assertSame('L', $one->variationLabel());
        $this->assertSame('', $none->variationLabel());
    }

    #[Test]
    public function sku_is_globally_unique(): void
    {
        ProductVariant::factory()->create(['sku' => 'DUPE-1']);

        $this->expectException(QueryException::class);

        ProductVariant::factory()->create(['sku' => 'DUPE-1']);
    }
}
