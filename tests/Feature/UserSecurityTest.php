<?php

namespace Tests\Feature;

use App\Models\LoginHistory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_available_from_login_url(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Connexion');
    }

    public function test_login_history_records_success_failure_and_logout(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat', [
            'username' => 'login-history-user',
            'password' => 'secret123',
        ]);

        $this->post(route('login.store'), [
            'username' => 'login-history-user',
            'password' => 'bad-password',
        ])->assertSessionHasErrors('username');

        $this->assertDatabaseHas('login_histories', [
            'username' => 'login-history-user',
            'status' => 'failed',
        ]);

        $this->post(route('login.store'), [
            'username' => 'login-history-user',
            'password' => 'secret123',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'username' => 'login-history-user',
            'status' => 'success',
        ]);

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'username' => 'login-history-user',
            'status' => 'logout',
        ]);
    }

    public function test_only_authorized_roles_can_open_login_history(): void
    {
        $this->seed(DatabaseSeeder::class);
        $direction = $this->userWithRole('direction');
        $secretariat = $this->userWithRole('secretariat');

        LoginHistory::query()->create([
            'user_id' => $direction->id,
            'username' => $direction->username,
            'status' => 'success',
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $this->actingAs($direction)
            ->get(route('login-histories.index'))
            ->assertOk()
            ->assertSee('Historique de connexion')
            ->assertSee('Connexion reussie');

        $this->actingAs($secretariat)
            ->get(route('login-histories.index'))
            ->assertForbidden();
    }

    public function test_user_can_update_profile_and_password(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat', [
            'password' => 'old-secret',
        ]);

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Mon profil');

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Secretaire Test',
                'email' => 'secretaire-test@example.com',
                'phone' => '70000000',
            ])
            ->assertRedirect(route('profile.show'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Secretaire Test',
            'email' => 'secretaire-test@example.com',
            'phone' => '70000000',
        ]);

        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => 'old-secret',
                'password' => 'new-secret',
                'password_confirmation' => 'new-secret',
            ])
            ->assertRedirect(route('profile.show'));

        $this->assertTrue(Hash::check('new-secret', $user->refresh()->password));
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'password_changed',
            'auditable_type' => User::class,
            'auditable_id' => (string) $user->id,
        ]);
    }

    public function test_admin_can_reset_staff_password(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('enseignant', [
            'password' => 'old-password',
        ]);

        $this->actingAs($admin)
            ->get(route('staff.show', $staff))
            ->assertOk()
            ->assertSee('Reinitialisation du mot de passe');

        $this->actingAs($admin)
            ->put(route('staff.reset-password', $staff), [
                'password' => 'temporary123',
                'password_confirmation' => 'temporary123',
            ])
            ->assertRedirect(route('staff.show', $staff));

        $this->assertTrue(Hash::check('temporary123', $staff->refresh()->password));
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'password_reset',
            'auditable_type' => User::class,
            'auditable_id' => (string) $staff->id,
        ]);
    }

    public function test_user_cannot_reset_own_password_from_staff_screen(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->userWithRole('admin', [
            'password' => 'old-admin-password',
        ]);

        $this->actingAs($admin)
            ->get(route('staff.show', $admin))
            ->assertOk()
            ->assertDontSee('Reinitialisation du mot de passe');

        $this->actingAs($admin)
            ->put(route('staff.reset-password', $admin), [
                'password' => 'new-admin-password',
                'password_confirmation' => 'new-admin-password',
            ])
            ->assertForbidden();

        $this->assertTrue(Hash::check('old-admin-password', $admin->refresh()->password));
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'username' => $role . '-security-test-' . uniqid(),
            'status' => 'active',
        ], $attributes));

        $user->assignRole($role);

        return $user;
    }
}
