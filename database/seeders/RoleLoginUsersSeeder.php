<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class RoleLoginUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        User::query()
            ->whereIn('username', ['parent', 'eleve'])
            ->whereIn('email', ['parent@lyceepagnidibsom.local', 'eleve@lyceepagnidibsom.local'])
            ->delete();

        $accounts = [
            'direction' => 'Direction',
            'secretariat' => 'Secretariat',
            'comptable' => 'Comptable',
            'enseignant' => 'Enseignant',
            'surveillant' => 'Surveillant',
        ];

        foreach ($accounts as $roleName => $displayName) {
            if (! Role::query()->where('name', $roleName)->exists()) {
                continue;
            }

            $user = User::query()->where('username', $roleName)->first();

            if (! $user) {
                $password = Str::password(20);
                $user = User::query()->create([
                    'username' => $roleName,
                    'name' => $displayName,
                    'email' => $roleName.'@lyceepagnidibsom.local',
                    'password' => Hash::make($password),
                    'status' => 'active',
                ]);
                $this->command?->line("Compte local {$roleName} créé avec le mot de passe : {$password}");
            }

            $user->syncRoles([$roleName]);
        }
    }
}
