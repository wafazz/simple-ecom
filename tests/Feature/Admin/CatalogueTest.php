<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToCreateDirectory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-001 / REQ-002 / REQ-008 */
class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    /** A product form submission: product fields plus at least one variation. */
    private function productPayload(array $overrides = [], array $variant = []): array
    {
        return array_merge([
            'category_id' => Category::factory()->create()->id,
            'name' => 'Hoodie',
            'product_type' => 'simple',
            'variants' => [array_merge([
                'id' => '',
                'sku' => 'HOOD-STD',
                'price' => '89.90',
                'stock_qty' => 5,
                'weight_g' => 700,
                'status' => 'active',
            ], $variant)],
        ], $overrides);
    }

    #[Test]
    public function a_guest_cannot_reach_any_catalogue_screen(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create();

        $this->get(route('admin.categories.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.products.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.products.variations.index', $product))->assertRedirect(route('admin.login'));
        $this->post(route('admin.categories.store'), ['name' => 'X'])->assertRedirect(route('admin.login'));
        $this->patch(route('admin.categories.toggle', $category))->assertRedirect(route('admin.login'));

        $this->assertDatabaseMissing('categories', ['name' => 'X']);
    }

    #[Test]
    public function a_category_slug_is_generated_from_the_name(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.categories.store'), ['name' => 'Baju Melayu & Kurta'])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', ['slug' => 'baju-melayu-kurta']);
    }

    #[Test]
    public function duplicate_category_slugs_are_rejected_with_a_field_error(): void
    {
        Category::factory()->create(['slug' => 'apparel']);

        $this->actingAs($this->admin)
            ->post(route('admin.categories.store'), ['name' => 'Apparel'])
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Category::count());
    }

    #[Test]
    public function a_category_can_keep_its_own_slug_when_edited(): void
    {
        $category = Category::factory()->create(['name' => 'Apparel', 'slug' => 'apparel']);

        $this->actingAs($this->admin)
            ->put(route('admin.categories.update', $category), ['name' => 'Apparel', 'slug' => 'apparel'])
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function deactivating_a_product_keeps_its_order_history_intact(): void
    {
        // The reason deactivate exists instead of delete (Planning §12.4).
        $variant = ProductVariant::factory()->create();
        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->create([
            'product_variant_id' => $variant->id,
            'product_name' => 'T-Shirt',
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.products.toggle', $variant->product_id))
            ->assertRedirect();

        $this->assertDatabaseHas('products', ['id' => $variant->product_id, 'is_active' => false]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'product_name' => 'T-Shirt']);
    }

    #[Test]
    public function a_product_and_its_variation_are_created_in_one_submission(): void
    {
        // One form defines both. A product with no variant cannot be sold, so
        // the two are never created separately.
        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), $this->productPayload())
            ->assertRedirect(route('admin.products.index'));

        $product = Product::where('slug', 'hoodie')->firstOrFail();

        $this->assertSame(1, $product->variants()->count());
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'sku' => 'HOOD-STD',
            'price_minor' => 8990,
            'stock_qty' => 5,
        ]);
    }

    #[Test]
    public function a_product_cannot_be_created_without_a_variation(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), $this->productPayload(['variants' => []]))
            ->assertSessionHasErrors('variants');

        $this->assertSame(0, Product::count());
    }

    #[Test]
    public function an_uploaded_image_is_stored_under_a_generated_name(): void
    {
        Storage::fake('uploads');
        $category = Category::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.products.store'), $this->productPayload([
            'category_id' => $category->id,
            'name' => 'Cap',
            'image' => UploadedFile::fake()->image('../../evil name.jpg'),
        ], ['sku' => 'CAP-STD']));

        $path = Product::where('slug', 'cap')->firstOrFail()->image_path;

        $this->assertNotNull($path);
        // The client's filename must not survive into the stored path.
        $this->assertStringNotContainsString('evil', $path);
        $this->assertStringNotContainsString('..', $path);
        Storage::disk('uploads')->assertExists($path);
    }

    #[Test]
    public function an_unwritable_uploads_folder_is_reported_not_crashed(): void
    {
        // Reproduces a live VPS failure. The `uploads` disk is configured
        // 'throw' => false, but Laravel catches only UnableToWriteFile —
        // Flysystem creates products/ on the first upload, and a failed mkdir
        // throws UnableToCreateDirectory, which escaped and produced a bare
        // 500 page with nothing an admin could act on.
        Storage::shouldReceive('disk')->with('uploads')->andThrow(
            UnableToCreateDirectory::atLocation('/var/www/public/uploads/products', 'Permission denied')
        );

        $category = Category::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), $this->productPayload([
                'category_id' => $category->id,
                'name' => 'Cap',
                'image' => UploadedFile::fake()->image('cap.jpg'),
            ], ['sku' => 'CAP-STD']))
            ->assertSessionHasErrors('image');

        // The message must not leak the server path it was told about.
        $this->assertStringNotContainsString(
            '/var/www',
            (string) session('errors')->first('image')
        );

        // Nothing half-saved: no product without its picture.
        $this->assertDatabaseMissing('products', ['slug' => 'cap']);
    }

    #[Test]
    public function a_silently_failed_write_never_saves_a_product_with_no_image(): void
    {
        // The other half of 'throw' => false: when the failure IS swallowed,
        // putFile() returns false, which would land in image_path as an empty
        // value — a product that looks saved and has no picture.
        Storage::shouldReceive('disk->putFile')->andReturn(false);

        $category = Category::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), $this->productPayload([
                'category_id' => $category->id,
                'name' => 'Cap',
                'image' => UploadedFile::fake()->image('cap.jpg'),
            ], ['sku' => 'CAP-STD']))
            ->assertSessionHasErrors('image');

        $this->assertDatabaseMissing('products', ['slug' => 'cap']);
    }

    #[Test]
    public function extra_gallery_views_are_stored_alongside_the_cover(): void
    {
        Storage::fake('uploads');
        $category = Category::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.products.store'), $this->productPayload([
            'category_id' => $category->id,
            'name' => 'Cap',
            'image' => UploadedFile::fake()->image('cover.jpg'),
            'gallery' => [
                UploadedFile::fake()->image('back.jpg'),
                UploadedFile::fake()->image('detail.jpg'),
            ],
        ], ['sku' => 'CAP-STD']))->assertSessionHasNoErrors();

        $product = Product::where('slug', 'cap')->firstOrFail();

        // The cover keeps its own column — the gallery holds only the extras.
        $this->assertNotNull($product->image_path);
        $this->assertCount(2, $product->images);
        $this->assertSame([1, 2], $product->images->pluck('sort_order')->all());
    }

    #[Test]
    public function a_gallery_view_ticked_for_removal_is_deleted_with_its_file(): void
    {
        Storage::fake('uploads');
        $product = Product::factory()->create(['slug' => 'cap']);
        $variant = ProductVariant::factory()->for($product)->create(['sku' => 'CAP-STD']);

        Storage::disk('uploads')->put('products/gone.jpg', 'x');
        $image = ProductImage::factory()->for($product)->create(['path' => 'products/gone.jpg']);

        $this->actingAs($this->admin)->put(route('admin.products.update', $product), $this->productPayload([
            'category_id' => $product->category_id,
            'name' => $product->name,
            'remove_images' => [$image->id],
            // The variant id must travel: without it the form would try to
            // INSERT a second row carrying the same SKU.
        ], ['id' => $variant->id, 'sku' => 'CAP-STD']))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        Storage::disk('uploads')->assertMissing('products/gone.jpg');
    }

    #[Test]
    public function a_forged_image_id_cannot_reach_another_products_gallery(): void
    {
        // §17 — a posted identifier is never trusted on its own.
        Storage::fake('uploads');
        $mine = Product::factory()->create(['slug' => 'mine']);
        $variant = ProductVariant::factory()->for($mine)->create(['sku' => 'MINE-STD']);

        $theirs = ProductImage::factory()->create();

        $this->actingAs($this->admin)->put(route('admin.products.update', $mine), $this->productPayload([
            'category_id' => $mine->category_id,
            'name' => $mine->name,
            'remove_images' => [$theirs->id],
        ], ['id' => $variant->id, 'sku' => 'MINE-STD']))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_images', ['id' => $theirs->id]);
    }

    #[Test]
    public function a_non_image_in_the_gallery_is_rejected(): void
    {
        Storage::fake('uploads');
        $category = Category::factory()->create();

        // The gallery must not be a looser way in than the cover field.
        $this->actingAs($this->admin)->post(route('admin.products.store'), $this->productPayload([
            'category_id' => $category->id,
            'name' => 'Payload',
            'gallery' => [UploadedFile::fake()->create('shell.php', 10, 'application/x-php')],
        ], ['sku' => 'PAY-STD']))->assertSessionHasErrors('gallery.0');

        $this->assertDatabaseMissing('products', ['slug' => 'payload']);
    }

    #[Test]
    public function a_non_image_upload_is_rejected(): void
    {
        Storage::fake('uploads');
        $category = Category::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.products.store'), $this->productPayload([
            'category_id' => $category->id,
            'name' => 'Payload',
            'image' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
        ], ['sku' => 'PAY-STD']))->assertSessionHasErrors('image');

        $this->assertDatabaseMissing('products', ['slug' => 'payload']);
    }
}
