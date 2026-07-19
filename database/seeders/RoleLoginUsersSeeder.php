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
