<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-011 */
class SettingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'store_name' => 'Kedai Contoh',
            'store_email' => 'hello@kedai.test',
            'store_phone' => '0123456789',
            'currency' => 'MYR',
            'pickup_postcode' => '11900',
            'pickup_state' => 'MY-07',
            'default_weight_g' => 600,
            'flat_shipping_fee' => '12.50',
            'low_stock_threshold' => 3,
        ], $overrides);
    }

    #[Test]
    public function a_guest_cannot_view_or_change_settings(): void
    {
        $this->get(route('admin.settings.edit'))->assertRedirect(route('admin.login'));
        $this->put(route('admin.settings.update'), $this->payload())->assertRedirect(route('admin.login'));

        $this->assertSame(0, Setting::count());
    }

    #[Test]
    public function settings_are_saved(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update'), $this->payload())
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertSame('Kedai Contoh', Setting::get('store_name'));
        $this->assertSame('MY-07', Setting::get('pickup_state'));
        $this->assertSame(3, Setting::getInt('low_stock_threshold'));
    }

    #[Test]
    public function the_flat_fee_is_entered_in_ringgit_and_stored_as_sen(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update'), $this->payload(['flat_shipping_fee' => '12.50']));

        $this->assertSame(1250, Setting::getInt('flat_shipping_fee_minor'));
        // The ringgit field itself is never persisted.
        $this->assertNull(Setting::cached()['flat_shipping_fee'] ?? null);
    }

    #[Test]
    public function saving_settings_busts_the_cache(): void
    {
        // Settings are cached per request cycle; a save must be visible at once
        // or the admin sees their own change fail to take effect.
        Setting::put('store_name', 'Old Name');
        $this->assertSame('Old Name', Setting::get('store_name'));

        $this->actingAs($this->admin)->put(route('admin.settings.update'), $this->payload());

        $this->assertSame('Kedai Contoh', Setting::get('store_name'));
    }

    #[Test]
    public function a_free_text_pickup_state_is_rejected(): void
    {
        // EasyParcel needs an ISO 3166-2 code, not a name (Planning §11.B.1).
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update'), $this->payload(['pickup_state' => 'Penang']))
            ->assertSessionHasErrors('pickup_state');
    }

    #[Test]
    public function an_invalid_postcode_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update'), $this->payload(['pickup_postcode' => 'ABCDE']))
            ->assertSessionHasErrors('pickup_postcode');
    }

    #[Test]
    public function a_zero_default_weight_is_rejected(): void
    {
        // A quotation must never be requested at zero weight (OQ-01).
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update'), $this->payload(['default_weight_g' => 0]))
            ->assertSessionHasErrors('default_weight_g');
    }

    #[Test]
    public function the_screen_shows_credential_status_but_never_credential_values(): void
    {
        config([
            'services.toyyibpay.secret_key' => 'SUPER-SECRET-TP-KEY',
            'services.toyyibpay.category_code' => 'cat-code',
            'services.easyparcel.client_id' => 'SUPER-SECRET-EP-ID',
            'services.easyparcel.client_secret' => 'SUPER-SECRET-EP-SECRET',
        ]);

        $this->actingAs($this->admin)->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Configured')
            ->assertDontSee('SUPER-SECRET-TP-KEY')
            ->assertDontSee('SUPER-SECRET-EP-ID')
            ->assertDontSee('SUPER-SECRET-EP-SECRET');
    }

    #[Test]
    public function unconfigured_credentials_are_reported_as_such(): void
    {
        config([
            'services.toyyibpay.secret_key' => null,
            'services.easyparcel.client_id' => null,
        ]);

        $this->actingAs($this->admin)->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Not configured');
    }

    #[Test]
    public function the_store_name_change_reaches_the_storefront(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update'), $this->payload(['store_name' => 'Butik Aisha']));

        $this->get(route('home'))->assertOk()->assertSee('Butik Aisha');
    }
}
