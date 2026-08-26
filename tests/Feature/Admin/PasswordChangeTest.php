<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** REQ-009 / REQ-010 — Planning §17.4, forced first-login password change. */
class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private function flaggedAdmin(): User
    {
        return User::factory()->create([
            'password' => Hash::make('password'),
            'must_change_password' => true,
        ]);
    }

    #[Test]
    public function an_admin_who_must_change_their_password_is_locked_out_of_every_screen(): void
    {
        $admin = $this->flaggedAdmin();

        foreach (['admin.dashboard', 'admin.orders.index', 'admin.settings.edit', 'admin.products.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertRedirect(route('admin.password.edit'));
        }
    }

    #[Test]
    public function they_can_still_reach_the_change_form_and_log_out(): void
    {
        // Locking someone out of logout as well would trap them.
        $admin = $this->flaggedAdmin();

        $this->actingAs($admin)->get(route('admin.password.edit'))->assertOk();
        $this->actingAs($admin)->post(route('admin.logout'))->assertRedirect(route('admin.login'));
    }

    #[Test]
    public function setting_a_password_clears_the_flag_and_restores_access(): void
    {
        $admin = $this->flaggedAdmin();

        $this->actingAs($admin)->put(route('admin.password.update'), [
            'current_password' => 'password',
            'password' => 'correct-horse-7-battery',
            'password_confirmation' => 'correct-horse-7-battery',
        ])->assertRedirect(route('admin.dashboard'));

        $admin->refresh();
        $this->assertFalse($admin->must_change_password);
        $this->assertTrue(Hash::check('correct-horse-7-battery', $admin->password));

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    #[Test]
    public function the_current_password_must_be_correct(): void
    {
        $admin = $this->flaggedAdmin();

        $this->actingAs($admin)->put(route('admin.password.update'), [
            'current_password' => 'not-the-password',
            'password' => 'correct-horse-7-battery',
            'password_confirmation' => 'correct-horse-7-battery',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue($admin->fresh()->must_change_password);
    }

    #[Test]
    public function a_weak_or_unconfirmed_password_is_rejected(): void
    {
        $admin = $this->flaggedAdmin();

        $this->actingAs($admin)->put(route('admin.password.update'), [
            'current_password' => 'password',
            'password' => 'short1',
            'password_confirmation' => 'short1',
        ])->assertSessionHasErrors('password');

        $this->actingAs($admin)->put(route('admin.password.update'), [
            'current_password' => 'password',
            'password' => 'correct-horse-7-battery',
            'password_confirmation' => 'different-7-entirely',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($admin->fresh()->must_change_password);
    }

    #[Test]
    public function an_admin_who_has_set_their_own_password_is_not_interrupted(): void
    {
        $admin = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    #[Test]
    public function the_seeded_development_admin_is_flagged_for_a_change(): void
    {
        // The credential highlighted at handoff review: it exists for local
        // work and must not survive first use anywhere else.
        $this->seed(AdminSeeder::class);

        $admin = User::where('email', 'admin@basic-ecom.test')->firstOrFail();

        $this->assertTrue($admin->must_change_password);
    }
}
