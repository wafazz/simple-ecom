<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-009 / REQ-010 — Planning §14. */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('');
    }

    #[Test]
    public function a_guest_hitting_the_dashboard_is_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    #[Test]
    public function an_active_admin_can_log_in(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.test']);

        $this->post(route('admin.login.attempt'), [
            'email' => 'admin@example.test',
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    #[Test]
    public function a_deactivated_admin_cannot_authenticate_at_all(): void
    {
        // is_active is part of the credentials, so this fails at attempt()
        // rather than being bounced by middleware afterwards.
        User::factory()->inactive()->create(['email' => 'gone@example.test']);

        $this->post(route('admin.login.attempt'), [
            'email' => 'gone@example.test',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function an_admin_deactivated_mid_session_is_ejected_on_the_next_request(): void
    {
        // Deactivation must bite immediately, not whenever the session expires.
        $admin = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $admin->update(['is_active' => false]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    #[Test]
    public function a_wrong_password_does_not_reveal_whether_the_account_exists(): void
    {
        User::factory()->create(['email' => 'real@example.test']);

        $known = $this->post(route('admin.login.attempt'), [
            'email' => 'real@example.test', 'password' => 'wrong',
        ]);
        $unknown = $this->post(route('admin.login.attempt'), [
            'email' => 'nobody@example.test', 'password' => 'wrong',
        ]);

        $this->assertSame(
            $known->getSession()->get('errors')->first('email'),
            $unknown->getSession()->get('errors')->first('email')
        );
        $this->assertGuest();
    }

    #[Test]
    public function login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'admin@example.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.login.attempt'), [
                'email' => 'admin@example.test', 'password' => 'wrong',
            ]);
        }

        $this->post(route('admin.login.attempt'), [
            'email' => 'admin@example.test', 'password' => 'password',
        ])->assertStatus(429);

        $this->assertGuest();
    }

    #[Test]
    public function the_session_id_changes_on_login_to_defeat_fixation(): void
    {
        User::factory()->create(['email' => 'admin@example.test']);

        $this->get(route('admin.login'));
        $before = session()->getId();

        $this->post(route('admin.login.attempt'), [
            'email' => 'admin@example.test', 'password' => 'password',
        ]);

        $this->assertNotSame($before, session()->getId());
    }

    #[Test]
    public function logout_requires_a_post(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/logout')
            ->assertStatus(405);
    }
}
