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
            RolesAndPermissionsSeeder::class,
            PlanSeeder::class,
            TestingAccountsSeeder::class,
        ]);

        $demoAdmin = User::where('email', 'demo@accounter.com')->first();
        if ($demoAdmin) {
            $superRole = \App\Models\Role::where('name', 'super-admin')->first();
            if ($superRole) {
                $demoAdmin->assignRole('super-admin');
            }
        }
    }
}
