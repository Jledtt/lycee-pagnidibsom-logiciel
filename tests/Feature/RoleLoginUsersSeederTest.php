<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleLoginUsersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_login_accounts_are_created_with_role_name_passwords(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['direction', 'secretariat', 'comptable', 'enseignant', 'surveillant', 'parent', 'eleve'] as $roleName) {
            $user = User::query()->where('username', $roleName)->firstOrFail();

            $this->assertSame('active', $user->status);
            $this->assertSame($roleName . '@lyceepagnidibsom.local', $user->email);
            $this->assertTrue($user->hasRole($roleName));
            $this->assertTrue(Hash::check($roleName, $user->password));
        }
    }

    public function test_admin_password_stays_on_existing_school_password(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue(Hash::check('Pagnidibsom', $admin->password));
    }
}
