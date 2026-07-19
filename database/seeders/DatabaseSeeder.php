<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AcademicBaselineSeeder::class,
            RoleLoginUsersSeeder::class,
        ]);

        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrateur',
                'email' => 'infoslyceepagnidibsom@gmail.com',
                'password' => Hash::make('Pagnidibsom'),
                'status' => 'active',
            ]
        );

        $admin->forceFill([
            'email' => 'infoslyceepagnidibsom@gmail.com',
            'password' => Hash::make('Pagnidibsom'),
            'status' => 'active',
        ])->save();

        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
    }
}
