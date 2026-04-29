<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            RolesAndPermissionsSeeder::class,
        ]);

        // Create Super Admin User
        $superAdmin = User::updateOrCreate(
            ['email' => 'admin@accounter.com'],
            [
                'name' => 'System Admin',
                'password' => \Hash::make('Admin123!'),
                'email_verified_at' => now(),
            ]
        );

        $superRole = \App\Models\Role::where('name', 'super-admin')->first();
        if ($superRole) {
            $superAdmin->assignRole('super-admin');
        }
    }
}
