<?php

namespace Tests\Feature\Admin;

use App\Enums\VariantStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * REQ-001 / REQ-002 — the product form defines the product AND its variations.
 */
class ProductVariantFormTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->category = Category::factory()->create();
    }

    private function row(array $overrides = []): array
    {
        return array_merge([
            'id' => '',
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

    private function payload(array $rows, array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'name' => 'T-Shirt',
            'product_type' => count($rows) > 1 ? 'variable' : 'simple',
            'variants' => $rows,
        ], $overrides);
    }

    #[Test]
    public function several_variations_are_created_in_one_submission(): void
    {
        $this->actingAs($this->admin)->post(route('admin.products.store'), $this->payload([
            $this->row(['sku' => 'TS-S-BLA', 'option1_value' => 'S', 'price' => '30.00']),
            $this->row(['sku' => 'TS-M-BLA', 'option1_value' => 'M', 'price' => '30.00']),
            $this->row(['sku' => 'TS-L-BLA', 'option1_value' => 'L', 'price' => '32.00']),
        ], ['product_type' => 'variable']))->assertRedirect(route('admin.products.index'));

        $product = Product::firstOrFail();

        $this->assertSame(3, $product->variants()->count());
        $this->assertSame(3200, $product->variants()->where('sku', 'TS-L-BLA')->value('price_minor'));
    }

    #[Test]
    public function editing_updates_existing_rows_adds_new_ones_and_removes_the_rest(): void
    {
        $product = Product::factory()->create();
        $keep = ProductVariant::factory()->for($product)->options('M', 'Black')
            ->create(['sku' => 'KEEP-1', 'price_minor' => 3000]);
        $drop = ProductVariant::factory()->for($product)->options('L', 'Black')->create(['sku' => 'DROP-1']);

        $this->actingAs($this->admin)->put(route('admin.products.update', $product), $this->payload([
            // existing row, price changed
            $this->row(['id' => $keep->id, 'sku' => 'KEEP-1', 'option1_value' => 'M', 'price' => '35.50']),
            // brand new row
            $this->row(['sku' => 'NEW-1', 'option1_value' => 'XL', 'price' => '38.00']),
            // DROP-1 omitted entirely
        ], ['product_type' => 'variable']))->assertRedirect();

        $this->assertSame(3550, $keep->fresh()->price_minor);
        $this->assertNotNull($product->variants()->where('sku', 'NEW-1')->first());
        $this->assertNull(ProductVariant::find($drop->id), 'An unused removed variant should be deleted.');
    }

    #[Test]
    public function removing_a_variation_that_has_been_ordered_deactivates_it_instead(): void
    {
        // order_items holds a restrictOnDelete foreign key because purchase
        // history must outlive the catalogue. Deleting would throw.
        $product = Product::factory()->create();
        $keep = ProductVariant::factory()->for($product)->options('M')->create(['sku' => 'KEEP-1']);
        $sold = ProductVariant::factory()->for($product)->options('L')->create(['sku' => 'SOLD-1']);

        OrderItem::factory()->for(Order::factory()->create())->create([
            'product_variant_id' => $sold->id,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.products.update', $product), $this->payload([
            $this->row(['id' => $keep->id, 'sku' => 'KEEP-1', 'option1_value' => 'M']),
        ]));

        $response->assertRedirect();

        $sold->refresh();
        $this->assertSame(VariantStatus::Inactive, $sold->status, 'A sold variant must survive removal.');
        $this->assertDatabaseHas('order_items', ['product_variant_id' => $sold->id]);

        // And the admin is told, rather than left to assume it vanished.
        $response->assertSessionHas('status', fn (string $m): bool => str_contains($m, 'SOLD-1'));
    }

    #[Test]
    public function a_duplicate_combination_in_the_same_form_is_a_field_error(): void
    {
        $this->actingAs($this->admin)->post(route('admin.products.store'), $this->payload([
            $this->row(['sku' => 'A-1', 'option1_value' => 'M']),
            $this->row(['sku' => 'A-2', 'option1_value' => 'M']),
        ], ['product_type' => 'variable']))->assertSessionHasErrors('variants.1.option1_value');

        $this->assertSame(0, Product::count());
    }

    #[Test]
    public function a_sku_repeated_within_the_form_is_caught_before_the_unique_index(): void
    {
        // Two new rows sharing a SKU would otherwise pass and die on the index.
        $this->actingAs($this->admin)->post(route('admin.products.store'), $this->payload([
            $this->row(['sku' => 'SAME', 'option1_value' => 'M']),
            $this->row(['sku' => 'SAME', 'option1_value' => 'L']),
        ], ['product_type' => 'variable']))->assertSessionHasErrors('variants.1.sku');
    }

    #[Test]
    public function a_sku_belonging_to_another_product_is_rejected(): void
    {
        ProductVariant::factory()->create(['sku' => 'TAKEN']);

        $this->actingAs($this->admin)->post(route('admin.products.store'), $this->payload([
            $this->row(['sku' => 'TAKEN']),
        ]))->assertSessionHasErrors('variants.0.sku');
    }

    #[Test]
    public function a_variant_keeps_its_own_sku_when_edited(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['sku' => 'MINE-1']);

        $this->actingAs($this->admin)->put(route('admin.products.update', $product), $this->payload([
            $this->row(['id' => $variant->id, 'sku' => 'MINE-1', 'option1_value' => '', 'option1_name' => '',
                'option2_value' => '', 'option2_name' => '']),
        ]))->assertSessionHasNoErrors();
    }

    #[Test]
    public function a_simple_product_stores_empty_option_slots_never_null(): void
    {
        $this->actingAs($this->admin)->post(route('admin.products.store'), $this->payload([
            $this->row(['sku' => 'TOTE', 'option1_name' => '', 'option1_value' => '',
                'option2_name' => '', 'option2_value' => '']),
        ]))->assertSessionHasNoErrors();

        $variant = ProductVariant::firstOrFail();

        $this->assertSame('', $variant->option1_value);
        $this->assertSame('', $variant->option2_value);
        $this->assertNotNull($variant->option1_value);
    }

    #[Test]
    public function an_option_name_without_a_value_is_collapsed(): void
    {
        $this->actingAs($this->admin)->post(route('admin.products.store'), $this->payload([
            $this->row(['sku' => 'HALF', 'option1_name' => 'Size', 'option1_value' => 'M',
                'option2_name' => 'Color', 'option2_value' => '']),
        ]))->assertSessionHasNoErrors();

        $variant = ProductVariant::firstOrFail();

        $this->assertSame('', $variant->option2_name);
        $this->assertSame('', $variant->option2_value);
    }

    #[Test]
    public function a_simple_product_may_not_carry_more_than_one_variation(): void
    {
        $this->actingAs($this->admin)->post(route('admin.products.store'), $this->payload([
            $this->row(['sku' => 'A-1', 'option1_value' => 'M']),
            $this->row(['sku' => 'A-2', 'option1_value' => 'L']),
        ], ['product_type' => 'simple']))->assertSessionHasErrors('variants');
    }

    #[Test]
    public function a_price_entered_in_ringgit_is_stored_as_sen(): void
    {
        $this->actingAs($this->admin)->post(route('admin.products.store'), $this->payload([
            $this->row(['price' => '32.50']),
        ]));

        $this->assertSame(3250, ProductVariant::firstOrFail()->price_minor);
    }

    #[Test]
    public function the_edit_form_prefills_every_existing_variation(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->for($product)->options('M', 'Black')->create(['sku' => 'TS-M-BLA', 'price_minor' => 3000]);
        ProductVariant::factory()->for($product)->options('L', 'Black')->create(['sku' => 'TS-L-BLA', 'price_minor' => 3200]);

        $this->actingAs($this->admin)->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('TS-M-BLA')
            ->assertSee('TS-L-BLA')
            ->assertSee('30.00')
            ->assertSee('32.00');
    }
}
