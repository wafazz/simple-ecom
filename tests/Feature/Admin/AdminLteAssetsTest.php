<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The admin panel uses the AdminLTE 4 template. Spec §6 requires assets to be
 * served locally — a CDN link would be a runtime dependency on someone else's
 * uptime and a privacy leak on every admin page load.
 */
class AdminLteAssetsTest extends TestCase
{
    use RefreshDatabase;

    private const VENDORED = [
        'public/vendor/adminlte/adminlte.min.css',
        'public/vendor/adminlte/adminlte.min.js',
        'public/vendor/bootstrap-icons/bootstrap-icons.css',
        'public/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2',
        'public/js/bootstrap.bundle.min.js',
    ];

    #[Test]
    public function every_admin_asset_is_present_on_disk(): void
    {
        foreach (self::VENDORED as $path) {
            $this->assertFileExists(base_path($path));
        }
    }

    #[Test]
    public function the_admin_panel_references_no_cdn(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('vendor/adminlte/adminlte.min.css', $html);
        $this->assertStringContainsString('vendor/adminlte/adminlte.min.js', $html);
        $this->assertStringContainsString('js/bootstrap.bundle.min.js', $html);

        foreach (['cdn.jsdelivr.net', 'cdnjs.cloudflare.com', 'unpkg.com', 'fonts.googleapis.com'] as $cdn) {
            $this->assertStringNotContainsString($cdn, $html, "Admin page must not load from {$cdn}.");
        }
    }

    #[Test]
    public function the_login_page_is_also_self_contained(): void
    {
        $html = $this->get(route('admin.login'))->assertOk()->getContent();

        $this->assertStringContainsString('vendor/adminlte/adminlte.min.css', $html);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $html);
    }

    #[Test]
    public function the_icon_css_font_paths_resolve_on_disk(): void
    {
        // The vendored CSS references fonts/ RELATIVE to itself. A wrong
        // directory layout shows up only as missing glyphs in a browser, so
        // resolve each url() against the stylesheet's own directory.
        //
        // Not asserted over HTTP: serving static files is the web server's
        // job, and Laravel's router would 404 them in any case.
        $cssPath = base_path('public/vendor/bootstrap-icons/bootstrap-icons.css');
        $dir = dirname($cssPath);

        preg_match_all('/url\("([^"?]+)/', file_get_contents($cssPath), $matches);

        $this->assertNotEmpty($matches[1], 'Expected font references in the icon CSS.');

        $woff2 = array_values(array_filter($matches[1], fn ($u) => str_ends_with($u, '.woff2')));
        $this->assertNotEmpty($woff2, 'Icon CSS must reference a woff2.');

        foreach ($woff2 as $relative) {
            $this->assertFileExists($dir.'/'.$relative);
        }
    }

    #[Test]
    public function the_sidebar_links_every_admin_area(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))->assertOk()->getContent();

        foreach (['admin.orders.index', 'admin.products.index', 'admin.categories.index',
            'admin.integrations.index', 'admin.settings.edit'] as $route) {
            $this->assertStringContainsString(route($route), $html);
        }
    }
}
