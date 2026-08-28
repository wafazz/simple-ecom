<?php

namespace Tests\Feature\Admin;

use App\Mail\OrderPaid;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\User;
use App\Support\IntegrationConfig;
use App\Support\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Outgoing mail over SMTP, configured from the admin panel.
 *
 * The password is the thing to be careful with: encrypted at rest, never
 * rendered back, and a blank box means "unchanged" rather than "delete" —
 * because the form cannot show what is stored, blank can only mean silence.
 */
class MailSettingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['email' => 'admin@example.com']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'mail_smtp_host' => 'mail.example.com',
            'mail_smtp_port' => '587',
            'smtp_username' => 'hello@example.com',
            'smtp_password' => 'super-secret',
            'mail_from_address' => 'hello@example.com',
            'mail_from_name' => 'Kedai Contoh',
        ], $overrides);
    }

    private function save(array $overrides = [])
    {
        return $this->actingAs($this->admin)
            ->put(route('admin.mail.update'), $this->payload($overrides));
    }

    // ------------------------------------------------------------- settings

    #[Test]
    public function an_admin_saves_the_smtp_credentials(): void
    {
        $this->save()->assertRedirect(route('admin.mail.edit'))->assertSessionHas('status');

        $this->assertSame('hello@example.com', IntegrationConfig::get('mail.smtp_username'));
        $this->assertSame('super-secret', IntegrationConfig::get('mail.smtp_password'));
        $this->assertSame('hello@example.com', MailSettings::fromAddress());
        $this->assertSame(587, MailSettings::port());
    }

    #[Test]
    public function the_password_is_encrypted_at_rest(): void
    {
        $this->save();

        $stored = \DB::table('secure_settings')->where('key', 'mail.smtp_password')->value('value');

        $this->assertNotSame('super-secret', $stored);
        $this->assertStringNotContainsString('super-secret', (string) $stored);
    }

    #[Test]
    public function the_password_is_never_rendered_back(): void
    {
        $this->save();

        $this->actingAs($this->admin)
            ->get(route('admin.mail.edit'))
            ->assertOk()
            ->assertDontSee('super-secret')
            ->assertSee('Stored');
    }

    #[Test]
    public function a_blank_password_leaves_the_stored_one_alone(): void
    {
        $this->save();

        // The form cannot show the password, so an empty box can only mean
        // "unchanged" — treating it as "clear" would silently break sending.
        $this->save(['smtp_password' => '', 'mail_from_name' => 'Renamed Shop']);

        $this->assertSame('super-secret', IntegrationConfig::get('mail.smtp_password'));
        $this->assertSame('Renamed Shop', MailSettings::fromName());
    }

    #[Test]
    public function an_unknown_port_is_refused(): void
    {
        $this->save(['mail_smtp_port' => '25'])->assertSessionHasErrors('mail_smtp_port');
    }

    #[Test]
    public function a_sender_that_is_not_an_address_is_refused(): void
    {
        $this->save(['mail_from_address' => 'not-an-email'])->assertSessionHasErrors('mail_from_address');
    }

    // ------------------------------------------------------------ transport

    #[Test]
    public function the_mailer_is_left_alone_until_it_is_configured(): void
    {
        MailSettings::apply();

        // An unconfigured shop keeps MAIL_MAILER, which writes to the log
        // rather than failing.
        $this->assertNotSame('mail.example.com', config('mail.mailers.smtp.host'));
    }

    #[Test]
    public function saved_settings_point_the_mailer_at_the_server(): void
    {
        $this->save();

        MailSettings::apply();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('mail.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('hello@example.com', config('mail.mailers.smtp.username'));
        $this->assertSame('hello@example.com', config('mail.from.address'));
    }

    #[Test]
    public function port_465_uses_implicit_tls(): void
    {
        $this->save(['mail_smtp_port' => '465']);

        MailSettings::apply();

        // 465 speaks TLS from the first byte; naming the wrong scheme hangs
        // until the timeout.
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
    }

    #[Test]
    public function port_2525_uses_starttls(): void
    {
        $this->save(['mail_smtp_port' => '2525']);

        MailSettings::apply();

        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
        $this->assertSame(2525, config('mail.mailers.smtp.port'));
    }

    #[Test]
    public function the_saved_port_comes_back_selected(): void
    {
        $this->save(['mail_smtp_port' => '465']);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.mail.edit'))->assertOk()->getContent();

        // The value was always stored correctly; it was the FORM that showed
        // 587, because no option carried `selected` and the browser fell back
        // to the first. Pressing save then wrote 587 over the real choice, so
        // this reads as "the port will not save".
        $this->assertMatchesRegularExpression(
            '/<option value="465"\s+selected/',
            $html,
            'The stored port is not marked selected, so the form shows the wrong one.',
        );

        $this->assertDoesNotMatchRegularExpression('/<option value="587"\s+selected/', $html);
    }

    #[Test]
    public function every_offered_port_survives_a_round_trip(): void
    {
        foreach (array_keys(MailSettings::PORTS) as $port) {
            $this->save(['mail_smtp_port' => (string) $port]);

            $html = $this->actingAs($this->admin)
                ->get(route('admin.mail.edit'))->assertOk()->getContent();

            $this->assertMatchesRegularExpression(
                '/<option value="'.$port.'"\s+selected/',
                $html,
                "Port {$port} does not come back selected.",
            );
        }
    }

    // ----------------------------------------------------------- the button

    #[Test]
    public function the_test_button_sends_a_real_message(): void
    {
        Mail::fake();
        $this->save();

        $this->actingAs($this->admin)
            ->post(route('admin.mail.test'), ['test_to' => 'someone@example.com'])
            ->assertRedirect();

        Mail::assertSent(OrderPaid::class, fn (OrderPaid $mail): bool => $mail->hasTo('someone@example.com') && $mail->sample);

        $result = session('test_result');
        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('someone@example.com', $result['summary']);
    }

    #[Test]
    public function testing_before_configuring_explains_rather_than_fails(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.mail.test'), ['test_to' => 'someone@example.com']);

        $result = session('test_result');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Not configured', $result['summary']);
        Mail::assertNothingSent();
    }

    #[Test]
    public function the_test_shows_what_a_customer_would_receive(): void
    {
        Mail::fake();
        $this->save();

        $this->actingAs($this->admin)
            ->post(route('admin.mail.test'), ['test_to' => 'someone@example.com']);

        Mail::assertSent(OrderPaid::class, function (OrderPaid $mail): bool {
            $html = $mail->render();

            return str_contains($html, 'sample')
                && str_contains($html, 'Home Kit 2026/27')
                // A printed nameset, so the admin sees one before a customer does.
                && str_contains($html, 'AZLAN 10')
                && str_contains($html, '203.00');
        });
    }

    #[Test]
    public function the_sample_subject_is_marked_as_one(): void
    {
        Mail::fake();
        $this->save();

        $this->actingAs($this->admin)
            ->post(route('admin.mail.test'), ['test_to' => 'someone@example.com']);

        Mail::assertSent(OrderPaid::class, fn (OrderPaid $mail): bool => str_starts_with($mail->envelope()->subject, '[Sample]'));
    }

    #[Test]
    public function the_test_never_creates_an_order(): void
    {
        Mail::fake();
        $this->save();

        $before = Order::withTrashed()->count();

        $this->actingAs($this->admin)
            ->post(route('admin.mail.test'), ['test_to' => 'someone@example.com']);

        // Nothing is saved: no row, no order number consumed, no stock moved.
        $this->assertSame($before, Order::withTrashed()->count());
        $this->assertSame(0, OrderItem::count());
    }

    #[Test]
    public function a_real_confirmation_is_not_marked_as_a_sample(): void
    {
        $order = Order::factory()->create();
        $order->setRelation('items', collect());

        $mail = new OrderPaid($order);

        $this->assertFalse($mail->sample);
        $this->assertStringNotContainsString('[Sample]', $mail->envelope()->subject);
        $this->assertStringNotContainsString('No order was placed', $mail->render());
    }

    #[Test]
    public function the_test_needs_a_real_address(): void
    {
        $this->save();

        $this->actingAs($this->admin)
            ->post(route('admin.mail.test'), ['test_to' => 'nope'])
            ->assertSessionHasErrors('test_to');
    }

    #[Test]
    public function a_failing_send_reports_what_went_wrong(): void
    {
        $this->save();

        // Nothing is listening on this host, so the transport raises rather
        // than the application deciding it succeeded.
        Setting::put('mail_smtp_host', '127.0.0.1');
        Setting::put('mail_smtp_port', '2525');
        config(['mail.mailers.smtp.timeout' => 1]);

        $this->actingAs($this->admin)
            ->post(route('admin.mail.test'), ['test_to' => 'someone@example.com']);

        $result = session('test_result');

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['raw'], 'The transport error must be shown; without it a failure is unactionable.');
    }

    // --------------------------------------------------------------- access

    #[Test]
    public function a_guest_cannot_reach_any_of_it(): void
    {
        $this->get(route('admin.mail.edit'))->assertRedirect(route('admin.login'));
        $this->put(route('admin.mail.update'), $this->payload())->assertRedirect(route('admin.login'));
        $this->post(route('admin.mail.test'), ['test_to' => 'x@example.com'])->assertRedirect(route('admin.login'));

        $this->assertNull(IntegrationConfig::get('mail.smtp_password'));
    }

    #[Test]
    public function the_screen_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.mail.edit'))
            ->assertOk()
            ->assertSee('SMTP server')
            ->assertSee('Not configured')
            ->assertSee('Send sample');
    }
}
