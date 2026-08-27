<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Return & Exchange Policy page, written by the admin.
 *
 * Two rules carry the weight. Nothing is published that the owner did not
 * type — a returns policy is something a customer may hold the shop to. And
 * what they type is TEXT: escaped before line breaks are added, so a policy can
 * never carry markup into the page.
 */
class ReturnPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function publish(string $body): void
    {
        Setting::put('return_policy', $body);
    }

    // ------------------------------------------------------------ publishing

    #[Test]
    public function the_page_is_absent_until_a_policy_is_written(): void
    {
        $this->get(route('policy.returns'))->assertNotFound();
    }

    #[Test]
    public function whitespace_alone_does_not_publish_a_policy(): void
    {
        $this->publish("   \n\n  \n");

        $this->get(route('policy.returns'))->assertNotFound();
    }

    #[Test]
    public function the_footer_link_appears_only_once_there_is_a_policy(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('Returns &amp; exchanges', false);

        $this->publish('Items may be returned within 7 days.');

        $this->get(route('home'))->assertOk()->assertSee('Returns &amp; exchanges', false);
    }

    #[Test]
    public function the_policy_is_shown_to_customers(): void
    {
        $this->publish('Items may be returned within 7 days of delivery.');

        $this->get(route('policy.returns'))
            ->assertOk()
            ->assertSee('Return &amp; Exchange Policy', false)
            ->assertSee('Items may be returned within 7 days of delivery.');
    }

    #[Test]
    public function blank_lines_become_separate_paragraphs(): void
    {
        $this->publish("First rule.\n\nSecond rule.");

        $html = $this->get(route('policy.returns'))->assertOk()->getContent();

        $this->assertStringContainsString('<p>First rule.</p>', $html);
        $this->assertStringContainsString('<p>Second rule.</p>', $html);
    }

    #[Test]
    public function a_single_newline_is_a_line_break_within_a_paragraph(): void
    {
        $this->publish("Line one.\nLine two.");

        $html = $this->get(route('policy.returns'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<p>Line one\.<br\s*\/?>\s*Line two\.<\/p>/', $html);
    }

    #[Test]
    public function the_page_says_when_it_was_last_updated(): void
    {
        $this->publish('Items may be returned within 7 days.');

        $this->get(route('policy.returns'))
            ->assertOk()
            ->assertSee('Last updated');
    }

    // ---------------------------------------------------------------- safety

    #[Test]
    public function a_policy_cannot_carry_markup_into_the_page(): void
    {
        $this->publish('<script>alert(1)</script> Returns accepted.');

        $html = $this->get(route('policy.returns'))->assertOk()->getContent();

        // The admin is trusted, but a trusted account is the one worth
        // stealing, and a returns policy has no reason to run anything.
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    #[Test]
    public function an_image_tag_is_shown_as_text_not_loaded(): void
    {
        $this->publish('<img src=x onerror=alert(1)> Returns accepted.');

        $html = $this->get(route('policy.returns'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    // ----------------------------------------------------------------- admin

    private function save(string $body)
    {
        return $this->actingAs(User::factory()->create())
            ->put(route('admin.policy.update'), ['return_policy' => $body]);
    }

    #[Test]
    public function an_admin_writes_and_replaces_the_policy(): void
    {
        $this->save('Returns accepted within 14 days.')
            ->assertRedirect(route('admin.policy.edit'))
            ->assertSessionHas('status');

        $this->get(route('policy.returns'))->assertOk()->assertSee('within 14 days');

        $this->save('Returns accepted within 30 days.');

        $this->get(route('policy.returns'))
            ->assertOk()
            ->assertSee('within 30 days')
            ->assertDontSee('within 14 days');
    }

    #[Test]
    public function clearing_the_field_unpublishes_the_page(): void
    {
        $this->publish('Returns accepted.');
        $this->get(route('policy.returns'))->assertOk();

        $this->save('')->assertSessionHas('status');

        $this->get(route('policy.returns'))->assertNotFound();
        $this->get(route('home'))->assertOk()->assertDontSee('Returns &amp; exchanges', false);
    }

    #[Test]
    public function clearing_says_the_page_is_no_longer_published(): void
    {
        $this->publish('Returns accepted.');

        $this->save('   ');

        $this->assertStringContainsString('no longer published', (string) session('status'));
    }

    #[Test]
    public function the_policy_has_its_own_screen(): void
    {
        $this->publish('Returns accepted within 14 days.');

        $this->actingAs(User::factory()->create())
            ->get(route('admin.policy.edit'))
            ->assertOk()
            ->assertSee('Return &amp; exchange policy', false)
            ->assertSee('Returns accepted within 14 days.')
            ->assertSee('Published');
    }

    #[Test]
    public function the_screen_says_when_nothing_is_published_yet(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.policy.edit'))
            ->assertOk()
            ->assertSee('Not published')
            ->assertDontSee('View page');
    }

    #[Test]
    public function the_settings_screen_no_longer_edits_the_policy(): void
    {
        $this->publish('Returns accepted within 14 days.');

        // One place to edit it. Two would drift, and saving Settings would risk
        // writing over writing.
        $this->actingAs(User::factory()->create())
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertDontSee('Returns accepted within 14 days.')
            ->assertDontSee('name="return_policy"', false);
    }

    #[Test]
    public function saving_settings_leaves_the_policy_alone(): void
    {
        $this->publish('Returns accepted within 14 days.');

        $this->actingAs(User::factory()->create())
            ->put(route('admin.settings.update'), $this->settingsPayload())
            ->assertSessionHasNoErrors();

        $this->get(route('policy.returns'))->assertOk()->assertSee('within 14 days');
    }

    #[Test]
    public function windows_line_endings_do_not_survive_into_the_page(): void
    {
        $this->save("First rule.\r\n\r\nSecond rule.");

        $html = $this->get(route('policy.returns'))->assertOk()->getContent();

        $this->assertStringContainsString('<p>First rule.</p>', $html);
        $this->assertStringContainsString('<p>Second rule.</p>', $html);
        $this->assertStringNotContainsString("\r", $html);
    }

    #[Test]
    public function a_guest_cannot_write_the_policy(): void
    {
        $this->put(route('admin.policy.update'), ['return_policy' => 'Anyone can write this.'])
            ->assertRedirect(route('admin.login'));

        $this->get(route('policy.returns'))->assertNotFound();
    }

    #[Test]
    public function a_guest_cannot_read_the_editor(): void
    {
        $this->get(route('admin.policy.edit'))->assertRedirect(route('admin.login'));
    }

    /**
     * The settings form posts every field at once, so a partial payload fails
     * validation on the others.
     *
     * @return array<string, mixed>
     */
    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'store_name' => 'Kedai Contoh',
            'store_email' => 'hello@example.com',
            'store_phone' => '0123456789',
            'currency' => 'MYR',
            'pickup_postcode' => '50000',
            'pickup_state' => 'MY-14',
            'default_weight_g' => 500,
            'flat_shipping_fee' => '10.00',
            'ship_west_first' => '8.00',
            'ship_west_next' => '3.00',
            'ship_east_first' => '15.00',
            'ship_east_next' => '12.00',
            'low_stock_threshold' => 5,
            'default_length_mm' => 250,
            'default_width_mm' => 180,
            'default_height_mm' => 80,
            'collection_lead_days' => 1,
        ], $overrides);
    }
}
