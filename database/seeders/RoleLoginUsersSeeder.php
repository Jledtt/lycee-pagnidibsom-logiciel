<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RoleLoginUsersSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            'direction' => 'Direction',
            'secretariat' => 'Secretariat',
            'comptable' => 'Comptable',
            'enseignant' => 'Enseignant',
            'surveillant' => 'Surveillant',
            'parent' => 'Parent',
            'eleve' => 'Eleve',
        ];

        foreach ($accounts as $roleName => $displayName) {
            if (! Role::query()->where('name', $roleName)->exists()) {
                continue;
            }

            $user = User::query()->updateOrCreate(
                ['username' => $roleName],
                [
                    'name' => $displayName,
                    'email' => $roleName . '@lyceepagnidibsom.local',
                    'password' => Hash::make($roleName),
                    'status' => 'active',
                ]
            );

            $user->syncRoles([$roleName]);
        }
    }
}
