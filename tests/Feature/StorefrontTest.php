<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_home_page_renders(): void
    {
        $this->get(route('home'))->assertOk();
    }

    #[Test]
    public function the_health_check_responds(): void
    {
        $this->get('/up')->assertOk();
    }

    #[Test]
    public function the_home_page_lists_only_active_categories(): void
    {
        Category::factory()->create(['name' => 'Apparel']);
        Category::factory()->inactive()->create(['name' => 'Discontinued']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Apparel')
            ->assertDontSee('Discontinued');
    }

    #[Test]
    public function the_store_name_comes_from_settings_and_falls_back_to_config(): void
    {
        // Empty settings table — the layout must still render.
        $this->get(route('home'))->assertOk()->assertSee(config('shop.store_name'));

        Setting::put('store_name', 'Kedai Contoh');

        $this->get(route('home'))->assertOk()->assertSee('Kedai Contoh');
    }

    #[Test]
    public function assets_are_served_locally_with_no_cdn_or_vite_reference(): void
    {
        // Planning §12.2 / spec §6 — Bootstrap is vendored, not linked from a
        // CDN, and there is no Vite manifest to depend on.
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('/css/bootstrap.min.css', $html);
        $this->assertStringContainsString('/css/app.css', $html);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $html);
        $this->assertStringNotContainsString('fonts.bunny.net', $html);
        $this->assertStringNotContainsString('/build/assets', $html);
    }

    #[Test]
    public function every_response_carries_a_correlation_id(): void
    {
        $this->get(route('home'))->assertOk()->assertHeader('X-Request-Id');
    }

    #[Test]
    public function a_missing_page_renders_the_custom_404(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee('Back to the shop');
    }
}
