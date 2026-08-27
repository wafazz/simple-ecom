<?php

namespace Tests\Feature\Admin;

use App\Models\Slide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Admin-managed home page banners.
 *
 * The rules that matter: the shop front never shows an empty band, one banner
 * is a hero rather than a carousel, and the wording stays real text so it is
 * never cropped away with the picture.
 */
class SlideTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        Storage::fake('uploads');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'headline' => 'Everyday pieces',
            'eyebrow' => 'PFC OFFICIAL MERCH',
            'subtext' => 'A small catalogue, chosen carefully.',
            'focal' => 'right',
            'sort_order' => 0,
            'is_active' => 1,
        ], $overrides);
    }

    private function banner(int $width = 2400, int $height = 1000): UploadedFile
    {
        return UploadedFile::fake()->image('banner.jpg', $width, $height);
    }

    // ------------------------------------------------------------ managing

    #[Test]
    public function an_admin_adds_a_banner_with_artwork(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.slides.store'), $this->payload(['image' => $this->banner()]))
            ->assertRedirect(route('admin.slides.index'));

        $slide = Slide::sole();

        $this->assertSame('Everyday pieces', $slide->headline);
        $this->assertSame('right', $slide->focal);
        $this->assertNotNull($slide->image_path);
        Storage::disk('uploads')->assertExists($slide->image_path);
    }

    #[Test]
    public function a_banner_may_be_wording_only(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.slides.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertNull(Slide::sole()->image_path);
    }

    #[Test]
    public function replacing_the_picture_deletes_the_old_file(): void
    {
        $this->actingAs($this->admin)->post(route('admin.slides.store'), $this->payload(['image' => $this->banner()]));
        $first = Slide::sole()->image_path;

        $this->actingAs($this->admin)->put(
            route('admin.slides.update', Slide::sole()),
            $this->payload(['image' => $this->banner()]),
        );

        $second = Slide::sole()->image_path;

        $this->assertNotSame($first, $second);
        Storage::disk('uploads')->assertExists($second);
        Storage::disk('uploads')->assertMissing($first);
    }

    #[Test]
    public function editing_the_wording_keeps_the_picture(): void
    {
        $this->actingAs($this->admin)->post(route('admin.slides.store'), $this->payload(['image' => $this->banner()]));
        $path = Slide::sole()->image_path;

        $this->actingAs($this->admin)->put(
            route('admin.slides.update', Slide::sole()),
            $this->payload(['headline' => 'New season']),
        );

        $this->assertSame('New season', Slide::sole()->headline);
        $this->assertSame($path, Slide::sole()->image_path, 'A text edit must not drop the artwork.');
        Storage::disk('uploads')->assertExists($path);
    }

    #[Test]
    public function deleting_a_banner_removes_its_file(): void
    {
        $this->actingAs($this->admin)->post(route('admin.slides.store'), $this->payload(['image' => $this->banner()]));
        $slide = Slide::sole();
        $path = $slide->image_path;

        $this->actingAs($this->admin)->delete(route('admin.slides.destroy', $slide));

        $this->assertSame(0, Slide::count());
        Storage::disk('uploads')->assertMissing($path);
    }

    #[Test]
    public function a_guest_cannot_manage_banners(): void
    {
        $this->post(route('admin.slides.store'), $this->payload())
            ->assertRedirect(route('admin.login'));

        $this->assertSame(0, Slide::count());
    }

    #[Test]
    public function the_admin_screens_render(): void
    {
        $slide = Slide::create($this->payload(['image_path' => 'slides/b.jpg']));

        $this->actingAs($this->admin)->get(route('admin.slides.index'))
            ->assertOk()->assertSee('2400 × 1000', false);

        $this->actingAs($this->admin)->get(route('admin.slides.create'))
            ->assertOk()->assertSee('Add banner');

        $this->actingAs($this->admin)->get(route('admin.slides.edit', $slide))
            ->assertOk()->assertSee($slide->headline);
    }

    #[Test]
    public function the_empty_state_invites_the_first_banner(): void
    {
        $this->actingAs($this->admin)->get(route('admin.slides.index'))
            ->assertOk()
            ->assertSee('No banners yet.')
            ->assertSee('built-in heading');
    }

    #[Test]
    public function a_new_banner_is_placed_after_the_existing_ones(): void
    {
        Slide::create($this->payload(['sort_order' => 7]));

        $this->actingAs($this->admin)->get(route('admin.slides.create'))
            ->assertOk()
            ->assertSee('value="8"', false);
    }

    #[Test]
    public function hiding_and_showing_a_banner_works_from_the_list(): void
    {
        $slide = Slide::create($this->payload());

        $this->actingAs($this->admin)->patch(route('admin.slides.toggle', $slide));
        $this->assertFalse($slide->fresh()->is_active);

        $this->actingAs($this->admin)->patch(route('admin.slides.toggle', $slide));
        $this->assertTrue($slide->fresh()->is_active);
    }

    // ---------------------------------------------------------- validation

    #[Test]
    public function a_headline_is_required(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.slides.store'), $this->payload(['headline' => '']))
            ->assertSessionHasErrors('headline');
    }

    #[Test]
    public function a_button_needs_both_a_label_and_a_link(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.slides.store'), $this->payload(['cta_label' => 'Shop']))
            ->assertSessionHasErrors('cta_url');

        $this->actingAs($this->admin)
            ->post(route('admin.slides.store'), $this->payload(['cta_url' => '/products']))
            ->assertSessionHasErrors('cta_label');
    }

    #[Test]
    public function artwork_below_the_minimum_width_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.slides.store'), $this->payload(['image' => $this->banner(600, 250)]))
            ->assertSessionHasErrors('image');

        $this->assertSame(0, Slide::count());
    }

    #[Test]
    public function a_non_image_upload_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.slides.store'), $this->payload([
                'image' => UploadedFile::fake()->create('shell.php', 20, 'application/x-php'),
            ]))
            ->assertSessionHasErrors('image');
    }

    #[Test]
    public function an_unknown_focal_point_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.slides.store'), $this->payload(['focal' => 'url(evil)']))
            ->assertSessionHasErrors('focal');
    }

    // --------------------------------------------------------- storefront

    #[Test]
    public function the_built_in_hero_shows_until_the_first_banner_exists(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Everyday pieces, made to be worn out.')
            ->assertDontSee('hero-slider');
    }

    #[Test]
    public function one_banner_renders_as_a_hero_not_a_carousel(): void
    {
        Slide::create($this->payload(['headline' => 'Only one']));

        $html = $this->get(route('home'))->assertOk()
            ->assertSee('Only one')
            ->getContent();

        // Arrows, dots and a timer that move between a single slide are chrome
        // that does nothing.
        $this->assertStringNotContainsString('carousel-item', $html);
        $this->assertStringNotContainsString('carousel-indicators', $html);
    }

    #[Test]
    public function two_banners_become_a_slider(): void
    {
        Slide::create($this->payload(['headline' => 'First', 'sort_order' => 0]));
        Slide::create($this->payload(['headline' => 'Second', 'sort_order' => 1]));

        $html = $this->get(route('home'))->assertOk()
            ->assertSee('First')->assertSee('Second')
            ->getContent();

        $this->assertStringContainsString('carousel-indicators', $html);
        $this->assertStringContainsString('data-bs-ride="carousel"', $html);
    }

    #[Test]
    public function banners_appear_in_their_set_order(): void
    {
        Slide::create($this->payload(['headline' => 'Comes second', 'sort_order' => 5]));
        Slide::create($this->payload(['headline' => 'Comes first', 'sort_order' => 1]));

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, 'Comes second'),
            strpos($html, 'Comes first'),
        );
    }

    #[Test]
    public function a_hidden_banner_is_not_shown(): void
    {
        Slide::create($this->payload(['headline' => 'Draft banner', 'is_active' => false]));

        // The only active set is empty, so the built-in hero returns.
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Draft banner')
            ->assertSee('Everyday pieces, made to be worn out.');
    }

    #[Test]
    public function the_wording_is_real_text_over_the_picture(): void
    {
        $slide = Slide::create($this->payload([
            'headline' => 'Searchable headline',
            'image_path' => 'slides/banner.jpg',
        ]));

        $html = $this->get(route('home'))->assertOk()->getContent();

        // Not baked into the artwork: it survives cropping and is indexable.
        $this->assertStringContainsString('<h1>Searchable headline</h1>', $html);
        $this->assertStringContainsString($slide->imageUrl(), $html);
        // Decorative backdrop — the words carry the meaning.
        $this->assertStringContainsString('alt=""', $html);
    }

    #[Test]
    public function a_button_without_a_link_is_not_rendered(): void
    {
        // Written straight to the model, bypassing the form rules, because the
        // view must not depend on validation having run.
        Slide::create($this->payload(['cta_label' => 'Dangling', 'cta_url' => null]));

        $this->get(route('home'))->assertOk()->assertDontSee('Dangling');
    }

    #[Test]
    public function the_focal_choice_reaches_the_page_as_a_safe_value(): void
    {
        Slide::create($this->payload(['focal' => 'right', 'image_path' => 'slides/b.jpg']));

        $this->get(route('home'))->assertOk()
            ->assertSee('object-position: right center', false);
    }
}
