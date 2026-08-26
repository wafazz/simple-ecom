<?php

namespace Tests\Feature\Admin;

use App\Models\SecureSetting;
use App\Models\User;
use App\Services\EasyParcelService;
use App\Services\ToyyibPayService;
use App\Support\IntegrationConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-010 / REQ-011 — admin-set integration credentials. */
class IntegrationCredentialTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    #[Test]
    public function a_guest_cannot_read_or_write_credentials(): void
    {
        $this->get(route('admin.integrations.index'))->assertRedirect(route('admin.login'));
        $this->put(route('admin.integrations.credentials'), ['toyyibpay_secret_key' => 'x'])
            ->assertRedirect(route('admin.login'));

        $this->assertSame(0, SecureSetting::count());
    }

    #[Test]
    public function a_saved_credential_is_encrypted_at_rest(): void
    {
        $this->actingAs($this->admin)->put(route('admin.integrations.credentials'), [
            'toyyibpay_secret_key' => 'tp-live-SUPERSECRET',
        ])->assertRedirect();

        $raw = DB::table('secure_settings')->where('key', 'toyyibpay.secret_key')->first();

        $this->assertNotSame('tp-live-SUPERSECRET', $raw->value);
        $this->assertStringNotContainsString('SUPERSECRET', $raw->value);
        $this->assertSame('tp-live-SUPERSECRET', IntegrationConfig::get('toyyibpay.secret_key'));
    }

    #[Test]
    public function the_form_never_renders_a_stored_secret(): void
    {
        // Spec §16: never expose API secrets to Blade.
        IntegrationConfig::put('toyyibpay.secret_key', 'tp-live-SUPERSECRET');
        IntegrationConfig::put('easyparcel.client_secret', 'ep-live-ALSOSECRET');

        $html = $this->actingAs($this->admin)
            ->get(route('admin.integrations.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('tp-live-SUPERSECRET', $html);
        $this->assertStringNotContainsString('ep-live-ALSOSECRET', $html);
        // Only a masked hint identifying it.
        $this->assertStringContainsString('CRET', $html);
        $this->assertStringContainsString('••••', $html);
    }

    #[Test]
    public function a_blank_field_keeps_the_stored_value(): void
    {
        // The form cannot show the current value, so blank must mean "leave it
        // alone" — never "clear it".
        IntegrationConfig::put('toyyibpay.secret_key', 'keep-me');

        $this->actingAs($this->admin)->put(route('admin.integrations.credentials'), [
            'toyyibpay_secret_key' => '',
            'toyyibpay_category_code' => 'cat-123',
        ])->assertRedirect();

        $this->assertSame('keep-me', IntegrationConfig::get('toyyibpay.secret_key'));
        $this->assertSame('cat-123', IntegrationConfig::get('toyyibpay.category_code'));
    }

    #[Test]
    public function clearing_is_an_explicit_action_and_falls_back_to_env(): void
    {
        config(['services.toyyibpay.secret_key' => 'from-env']);
        IntegrationConfig::put('toyyibpay.secret_key', 'from-admin');

        $this->assertSame('from-admin', IntegrationConfig::get('toyyibpay.secret_key'));

        $this->actingAs($this->admin)
            ->delete(route('admin.integrations.credentials.clear', 'toyyibpay.secret_key'))
            ->assertRedirect();

        $this->assertSame('from-env', IntegrationConfig::get('toyyibpay.secret_key'));
    }

    #[Test]
    public function an_admin_value_overrides_env_for_the_services(): void
    {
        config([
            'services.toyyibpay.secret_key' => 'env-secret',
            'services.toyyibpay.category_code' => 'env-cat',
            'services.easyparcel.client_id' => null,
            'services.easyparcel.client_secret' => null,
        ]);

        $this->assertTrue(app(ToyyibPayService::class)->isConfigured());
        $this->assertFalse(app(EasyParcelService::class)->isConfigured());

        IntegrationConfig::put('easyparcel.client_id', 'admin-id');
        IntegrationConfig::put('easyparcel.client_secret', 'admin-secret');

        // Rebuilt from the container, so the new values are picked up.
        $this->assertTrue(app(EasyParcelService::class)->isConfigured());
    }

    #[Test]
    public function only_known_credentials_can_be_written_or_cleared(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        IntegrationConfig::put('app.key', 'nope');
    }

    #[Test]
    public function clearing_an_unknown_credential_is_a_404(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.integrations.credentials.clear', 'app.key'))
            ->assertNotFound();
    }

    #[Test]
    public function a_credential_is_never_written_to_the_log(): void
    {
        $logFile = storage_path('logs/laravel-'.now()->format('Y-m-d').'.log');
        $before = file_exists($logFile) ? filesize($logFile) : 0;

        $this->actingAs($this->admin)->put(route('admin.integrations.credentials'), [
            'toyyibpay_secret_key' => 'tp-NEVERLOGGED',
        ])->assertRedirect();

        if (file_exists($logFile)) {
            $written = substr(file_get_contents($logFile), $before);
            $this->assertStringNotContainsString('NEVERLOGGED', $written);
        }

        $this->assertTrue(true);
    }

    #[Test]
    public function the_model_hides_the_value_from_array_and_json(): void
    {
        IntegrationConfig::put('toyyibpay.secret_key', 'hidden-please');

        $model = SecureSetting::firstOrFail();

        $this->assertArrayNotHasKey('value', $model->toArray());
        $this->assertStringNotContainsString('hidden-please', $model->toJson());
    }
}
