<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleLoginUsersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_login_accounts_are_created_with_role_name_passwords(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['direction', 'secretariat', 'comptable', 'enseignant', 'surveillant'] as $roleName) {
            $user = User::query()->where('username', $roleName)->firstOrFail();

            $this->assertSame('active', $user->status);
            $this->assertSame($roleName.'@lyceepagnidibsom.local', $user->email);
            $this->assertTrue($user->hasRole($roleName));
            $this->assertTrue(Hash::check($roleName, $user->password));
        }

        $this->assertDatabaseMissing('users', ['username' => 'parent']);
        $this->assertDatabaseMissing('users', ['username' => 'eleve']);
    }

    public function test_old_parent_and_student_default_accounts_are_removed(): void
    {
        User::factory()->create([
            'username' => 'parent',
            'email' => 'parent@lyceepagnidibsom.local',
        ]);
        User::factory()->create([
            'username' => 'eleve',
            'email' => 'eleve@lyceepagnidibsom.local',
        ]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseMissing('users', ['username' => 'parent']);
        $this->assertDatabaseMissing('users', ['username' => 'eleve']);
    }

    public function test_old_parent_and_student_roles_are_removed(): void
    {
        Role::findOrCreate('parent');
        Role::findOrCreate('eleve');

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseMissing('roles', ['name' => 'parent']);
        $this->assertDatabaseMissing('roles', ['name' => 'eleve']);
    }

    public function test_configured_admin_password_is_used_only_when_the_account_is_created(): void
    {
        config()->set('lpp.admin_password', 'configured-admin-password');
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue(Hash::check('configured-admin-password', $admin->password));

        config()->set('lpp.admin_password', 'replacement-password');
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(Hash::check('configured-admin-password', $admin->refresh()->password));
        $this->assertFalse(Hash::check('replacement-password', $admin->password));
    }

    public function test_production_seed_without_admin_password_does_not_create_admin(): void
    {
        Log::spy();
        config()->set('lpp.admin_password');
        $this->app->detectEnvironment(fn () => 'production');

        try {
            (new DatabaseSeeder)->setContainer($this->app)->run();
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }

        $this->assertDatabaseMissing('users', ['username' => 'admin']);
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Compte administrateur ignoré : LPP_ADMIN_PASSWORD est vide en production.');
    }

    public function test_existing_admin_is_not_modified_when_production_password_is_missing(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin',
            'email' => 'admin-existing@example.test',
            'password' => 'existing-password',
            'status' => 'inactive',
        ]);
        $originalUpdatedAt = $admin->updated_at;
        config()->set('lpp.admin_password');
        $this->app->detectEnvironment(fn () => 'production');

        try {
            (new DatabaseSeeder)->setContainer($this->app)->run();
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }

        $admin->refresh();
        $this->assertSame('admin-existing@example.test', $admin->email);
        $this->assertSame('inactive', $admin->status);
        $this->assertTrue(Hash::check('existing-password', $admin->password));
        $this->assertTrue($originalUpdatedAt->equalTo($admin->updated_at));
    }
}
