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
        ]);

        $superRole = \App\Models\Role::where('name', 'super-admin')->first();

        $user = User::factory()->create([
            'name' => 'Test Admin',
            'email' => 'test@example.com',
            'role_id' => $superRole?->id,
        ]);
        if ($superRole) {
            $user->assignRole('super-admin');
        }
    }
}
