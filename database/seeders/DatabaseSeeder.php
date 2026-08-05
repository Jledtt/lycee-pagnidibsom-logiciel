<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AcademicBaselineSeeder::class,
            RoleLoginUsersSeeder::class,
        ]);

        $password = trim((string) config('lpp.admin_password'));

        if (app()->isProduction() && $password === '') {
            Log::warning('Compte administrateur ignoré : LPP_ADMIN_PASSWORD est vide en production.');

            return;
        }

        $admin = User::query()->where('username', 'admin')->first();

        if (! $admin) {
            if ($password === '') {
                $password = Str::password(20);
                $this->command?->warn('Mot de passe administrateur local généré : '.$password);
            }

            $admin = User::query()->create([
                'username' => 'admin',
                'name' => 'Administrateur',
                'email' => 'infoslyceepagnidibsom@gmail.com',
                'password' => Hash::make($password),
                'status' => 'active',
            ]);
        }

        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
    }
}
