<?php

namespace Tests\Feature\Admin;

use App\Models\IntegrationToken;
use App\Models\SecureSetting;
use App\Models\Setting;
use App\Models\User;
use App\Services\EasyParcelService;
use App\Services\ToyyibPayService;
use App\Support\IntegrationConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
        $this->put(route('admin.integrations.credentials', 'toyyibpay'), ['toyyibpay_secret_key' => 'x'])
            ->assertRedirect(route('admin.login'));

        $this->assertSame(0, SecureSetting::count());
    }

    #[Test]
    public function a_saved_credential_is_encrypted_at_rest(): void
    {
        $this->actingAs($this->admin)->put(route('admin.integrations.credentials', 'toyyibpay'), [
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

        $this->actingAs($this->admin)->put(route('admin.integrations.credentials', 'toyyibpay'), [
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
    public function each_provider_form_can_only_write_its_own_credentials(): void
    {
        // The forms are separate, and so is what each submission may touch:
        // posting EasyParcel fields to the ToyyibPay form changes nothing.
        $this->actingAs($this->admin)->put(route('admin.integrations.credentials', 'toyyibpay'), [
            'toyyibpay_secret_key' => 'tp-value',
            'easyparcel_client_secret' => 'should-be-ignored',
        ])->assertRedirect();

        $this->assertSame('tp-value', IntegrationConfig::get('toyyibpay.secret_key'));
        $this->assertFalse(IntegrationConfig::isSetByAdmin('easyparcel.client_secret'));
    }

    #[Test]
    public function an_unknown_provider_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.integrations.credentials', 'toyyibpay'), [])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->put('/admin/integrations/stripe/credentials', ['x' => 'y'])
            ->assertNotFound();
    }

    #[Test]
    public function each_provider_has_its_own_form_on_the_page(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.integrations.index'))->assertOk()->getContent();

        $this->assertStringContainsString(
            'action="'.route('admin.integrations.credentials', 'toyyibpay').'"', $html
        );
        $this->assertStringContainsString(
            'action="'.route('admin.integrations.credentials', 'easyparcel').'"', $html
        );
        $this->assertStringContainsString('Save ToyyibPay credentials', $html);
        $this->assertStringContainsString('Save EasyParcel credentials', $html);
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

        $this->actingAs($this->admin)->put(route('admin.integrations.credentials', 'toyyibpay'), [
            'toyyibpay_secret_key' => 'tp-NEVERLOGGED',
        ])->assertRedirect();

        if (file_exists($logFile)) {
            $written = substr(file_get_contents($logFile), $before);
            $this->assertStringNotContainsString('NEVERLOGGED', $written);
        }

        $this->assertTrue(true);
    }

    #[Test]
    public function each_provider_can_be_switched_between_sandbox_and_production(): void
    {
        config(['services.toyyibpay.sandbox' => true]);

        $this->assertSame('sandbox', IntegrationConfig::mode('toyyibpay'));

        $this->actingAs($this->admin)
            ->patch(route('admin.integrations.mode', 'toyyibpay'), ['mode' => 'production'])
            ->assertRedirect();

        $this->assertSame('production', IntegrationConfig::mode('toyyibpay'));
        $this->assertFalse(IntegrationConfig::isSandbox('toyyibpay'));

        // The other provider is untouched.
        $this->assertSame('sandbox', IntegrationConfig::mode('easyparcel'));
    }

    #[Test]
    public function the_toyyibpay_mode_selects_the_gateway_host(): void
    {
        // Both hosts are verified; switching must actually change where
        // requests go, not merely relabel a badge.
        IntegrationConfig::setMode('toyyibpay', 'sandbox');
        IntegrationConfig::put('toyyibpay.secret_key', 'k');
        IntegrationConfig::put('toyyibpay.category_code', 'c');

        Http::fake(['*' => Http::response([['BillCode' => 'X']], 200)]);
        app(ToyyibPayService::class)->probe();
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://dev.toyyibpay.com'));

        IntegrationConfig::setMode('toyyibpay', 'production');
        Http::fake(['*' => Http::response([['BillCode' => 'X']], 200)]);
        app(ToyyibPayService::class)->probe();
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://toyyibpay.com'));
    }

    #[Test]
    public function an_invalid_mode_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('admin.integrations.mode', 'toyyibpay'), ['mode' => 'staging'])
            ->assertSessionHasErrors('mode');
    }

    #[Test]
    public function testing_toyyibpay_reports_the_response_field_names(): void
    {
        // This is the point of the button: those names are what payment
        // verification is waiting on (OQ-11).
        IntegrationConfig::put('toyyibpay.secret_key', 'k');
        IntegrationConfig::put('toyyibpay.category_code', 'c');

        Http::fake(['*getBillTransactions' => Http::response([[
            'billpaymentStatus' => '1',
            'billpaymentAmount' => '70.00',
            'billExternalReferenceNo' => 'ORD-1',
        ]], 200)]);

        $this->actingAs($this->admin)
            ->post(route('admin.integrations.test', 'toyyibpay'), ['bill_code' => 'abc123'])
            ->assertRedirect()
            ->assertSessionHas('test_result', fn (array $r): bool => $r['ok']
                && in_array('billpaymentStatus', $r['fields'], true)
                && in_array('billpaymentAmount', $r['fields'], true));
    }

    #[Test]
    public function a_connection_test_never_reports_response_values(): void
    {
        // The body can carry customer data; only key names may surface.
        IntegrationConfig::put('toyyibpay.secret_key', 'k');
        IntegrationConfig::put('toyyibpay.category_code', 'c');

        Http::fake(['*getBillTransactions' => Http::response([[
            'billpaymentStatus' => '1',
            'billTo' => 'Aisha Rahman',
            'billEmail' => 'aisha@example.test',
        ]], 200)]);

        $this->actingAs($this->admin)
            ->post(route('admin.integrations.test', 'toyyibpay'), ['bill_code' => 'abc123']);

        $result = session('test_result');
        $encoded = json_encode($result);

        $this->assertStringNotContainsString('Aisha Rahman', $encoded);
        $this->assertStringNotContainsString('aisha@example.test', $encoded);
        $this->assertContains('billTo', $result['fields']);
    }

    #[Test]
    public function a_failing_test_says_what_went_wrong(): void
    {
        IntegrationConfig::put('toyyibpay.secret_key', 'k');
        IntegrationConfig::put('toyyibpay.category_code', 'c');

        Http::fake(['*getBillTransactions' => Http::response('<html>502</html>', 200)]);

        $this->actingAs($this->admin)
            ->post(route('admin.integrations.test', 'toyyibpay'))
            ->assertSessionHas('test_result', fn (array $r): bool => $r['ok'] === false
                && str_contains($r['summary'], 'not JSON'));
    }

    #[Test]
    public function testing_an_unconfigured_provider_explains_rather_than_erroring(): void
    {
        config(['services.toyyibpay.secret_key' => null, 'services.toyyibpay.category_code' => null]);

        $this->actingAs($this->admin)
            ->post(route('admin.integrations.test', 'toyyibpay'))
            ->assertRedirect()
            ->assertSessionHas('test_result', fn (array $r): bool => $r['ok'] === false
                && str_contains($r['summary'], 'No secret key'));
    }

    #[Test]
    public function testing_easyparcel_runs_a_real_quotation(): void
    {
        config([
            'services.easyparcel.client_id' => 'id',
            'services.easyparcel.client_secret' => 'secret',
        ]);
        Setting::put('pickup_postcode', '50000');
        Setting::put('pickup_state', 'MY-14');
        IntegrationToken::create([
            'provider' => 'easyparcel', 'access_token' => 'a', 'refresh_token' => 'r',
            'expires_at' => now()->addHours(5),
        ]);

        Http::fake(['*shipment/quotations' => Http::response(['data' => [['quotations' => [[
            'courier' => ['service_id' => 'S1', 'courier_name' => 'J&T', 'service_name' => 'Standard'],
            'pricing' => ['total_amount' => '10.84'],
        ]]]]], 200)]);

        $this->actingAs($this->admin)
            ->post(route('admin.integrations.test', 'easyparcel'))
            ->assertSessionHas('test_result', fn (array $r): bool => $r['ok']
                && str_contains($r['detail'], 'J&T'));
    }

    #[Test]
    public function testing_easyparcel_while_disconnected_says_so(): void
    {
        config([
            'services.easyparcel.client_id' => 'id',
            'services.easyparcel.client_secret' => 'secret',
        ]);
        Http::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.integrations.test', 'easyparcel'))
            ->assertSessionHas('test_result', fn (array $r): bool => $r['ok'] === false
                && str_contains($r['summary'], 'Not connected'));

        Http::assertNothingSent();
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
