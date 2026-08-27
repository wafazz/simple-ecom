<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Stylesheets and scripts carry a fingerprint.
 *
 * There is no build step here: CSS and JS are plain files served by nginx, so
 * without this their URLs never change and a browser keeps whatever it already
 * has. A deploy then applies on the server and not in the visitor's browser,
 * and the two halves of a rename drift apart — markup asking for one class name
 * while the cached stylesheet still defines the other.
 */
class AssetVersioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Asset::flush();
    }

    #[Test]
    public function a_real_file_gets_its_modification_time(): void
    {
        $this->assertMatchesRegularExpression(
            '/\/css\/storefront\.css\?v=\d+$/',
            Asset::url('css/storefront.css'),
        );
    }

    #[Test]
    public function the_fingerprint_changes_when_the_file_does(): void
    {
        $path = public_path('css/storefront.css');
        $original = filemtime($path);

        try {
            $before = Asset::url('css/storefront.css');

            touch($path, $original + 60);
            Asset::flush();

            $this->assertNotSame($before, Asset::url('css/storefront.css'));
        } finally {
            touch($path, $original);
            Asset::flush();
        }
    }

    #[Test]
    public function a_missing_file_still_yields_a_usable_url(): void
    {
        // A broken page either way; a fingerprint of "false" would only make it
        // harder to see why.
        $url = Asset::url('css/does-not-exist.css');

        $this->assertStringContainsString('css/does-not-exist.css', $url);
        $this->assertStringNotContainsString('?v=', $url);
    }

    #[Test]
    public function the_storefront_serves_fingerprinted_assets(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        foreach (['css/storefront.css', 'css/bootstrap.min.css', 'js/app.js'] as $asset) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($asset, '/').'\?v=\d+/',
                $html,
                "{$asset} is served without a fingerprint, so a browser may keep a stale copy.",
            );
        }
    }

    #[Test]
    public function the_admin_serves_fingerprinted_assets(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))->assertOk()->getContent();

        foreach (['css/app.css', 'js/app.js'] as $asset) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($asset, '/').'\?v=\d+/',
                $html,
            );
        }
    }

    #[Test]
    public function the_login_page_serves_fingerprinted_assets(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('css/app.css?v=', false);
    }
}
