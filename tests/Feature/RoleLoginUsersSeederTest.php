<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
            $this->assertSame($roleName . '@lyceepagnidibsom.local', $user->email);
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

    public function test_admin_password_stays_on_existing_school_password(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue(Hash::check('Pagnidibsom', $admin->password));
    }
}
