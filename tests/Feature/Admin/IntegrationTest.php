<?php

namespace Tests\Feature\Admin;

use App\Models\IntegrationToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-006 — OAuth connect flow (Planning §11.B.3). */
class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();

        config([
            'services.easyparcel.client_id' => 'test-client',
            'services.easyparcel.client_secret' => 'test-secret',
            'services.easyparcel.oauth_url' => 'https://api.easyparcel.com/oauth',
        ]);
    }

    #[Test]
    public function a_guest_cannot_reach_the_integrations_screen(): void
    {
        $this->get(route('admin.integrations.index'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.integrations.connect'))->assertRedirect(route('admin.login'));
    }

    #[Test]
    public function connecting_redirects_to_the_provider_with_a_state_nonce(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.integrations.connect'));

        $location = $response->headers->get('Location');

        $this->assertStringStartsWith('https://api.easyparcel.com/oauth/login?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('test-client', $query['client_id']);
        $this->assertNotEmpty($query['state']);
        $this->assertSame($query['state'], session('easyparcel_oauth_state'));
    }

    #[Test]
    public function a_callback_with_a_mismatched_state_is_rejected(): void
    {
        // Without this check an attacker's authorization code could bind the
        // store to THEIR EasyParcel account and we would ship on their credit.
        Http::fake();

        $this->actingAs($this->admin)
            ->withSession(['easyparcel_oauth_state' => 'the-real-nonce'])
            ->get(route('admin.integrations.callback', ['code' => 'attacker-code', 'state' => 'wrong-nonce']))
            ->assertRedirect(route('admin.integrations.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, IntegrationToken::count());
        Http::assertNothingSent();
    }

    #[Test]
    public function a_callback_with_no_state_in_session_is_rejected(): void
    {
        Http::fake();

        $this->actingAs($this->admin)
            ->get(route('admin.integrations.callback', ['code' => 'code', 'state' => 'anything']))
            ->assertSessionHas('error');

        $this->assertSame(0, IntegrationToken::count());
        Http::assertNothingSent();
    }

    #[Test]
    public function a_valid_callback_stores_encrypted_tokens(): void
    {
        Http::fake(['*oauth/token' => Http::response([
            'access_token' => 'the-access-token',
            'refresh_token' => 'the-refresh-token',
            'expires_in' => 36000,
        ], 200)]);

        $this->actingAs($this->admin)
            ->withSession(['easyparcel_oauth_state' => 'nonce-123'])
            ->get(route('admin.integrations.callback', ['code' => 'auth-code', 'state' => 'nonce-123']))
            ->assertRedirect(route('admin.integrations.index'))
            ->assertSessionHas('status');

        $token = IntegrationToken::firstOrFail();
        $this->assertSame('the-access-token', $token->access_token);

        // Ciphertext at rest, not plaintext.
        $raw = \DB::table('integration_tokens')->first();
        $this->assertStringNotContainsString('the-access-token', $raw->access_token);
    }

    #[Test]
    public function the_state_nonce_is_single_use(): void
    {
        Http::fake(['*oauth/token' => Http::response([
            'access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 100,
        ], 200)]);

        $session = ['easyparcel_oauth_state' => 'nonce-123'];

        $this->actingAs($this->admin)->withSession($session)
            ->get(route('admin.integrations.callback', ['code' => 'c', 'state' => 'nonce-123']))
            ->assertSessionHas('status');

        // Replaying the same callback must fail: the nonce was pulled.
        $this->actingAs($this->admin)
            ->get(route('admin.integrations.callback', ['code' => 'c', 'state' => 'nonce-123']))
            ->assertSessionHas('error');
    }

    #[Test]
    public function the_screen_never_renders_token_material(): void
    {
        IntegrationToken::create([
            'provider' => 'easyparcel',
            'access_token' => 'SUPER-SECRET-ACCESS',
            'refresh_token' => 'SUPER-SECRET-REFRESH',
            'expires_at' => now()->addHours(10),
            'connected_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.integrations.index'))
            ->assertOk()
            ->assertSee('Connected')
            ->assertDontSee('SUPER-SECRET-ACCESS')
            ->assertDontSee('SUPER-SECRET-REFRESH')
            ->assertDontSee('test-secret');
    }

    #[Test]
    public function disconnecting_removes_the_tokens(): void
    {
        IntegrationToken::create([
            'provider' => 'easyparcel', 'access_token' => 'a', 'refresh_token' => 'r',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.integrations.disconnect'))
            ->assertRedirect(route('admin.integrations.index'));

        $this->assertSame(0, IntegrationToken::count());
    }
}
