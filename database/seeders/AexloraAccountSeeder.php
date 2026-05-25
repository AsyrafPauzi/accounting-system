<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\ProvisionsTenantDatabase;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AexloraAccountSeeder extends Seeder
{
    use ProvisionsTenantDatabase;

    /**
     * Provision admin@aexlora.com (if missing) and assign Corporate for 1 month.
     *
     * Run after PlanSeeder, or on an environment where plans already exist:
     *   php artisan db:seed --class=AexloraAccountSeeder
     *
     * If the user already registered via /register, only the subscription is updated.
     * Login password when provisioned here: env TESTING_SEED_PASSWORD, or "password".
     */
    public function run(): void
    {
        $email = 'admin@aexlora.com';
        $tenantDisplayName = 'Aexlora';
        $planSlug = 'corporate';

        $plan = Plan::where('slug', $planSlug)->first();
        if (! $plan) {
            $this->command->error("Plan {$planSlug} not found. Run PlanSeeder first.");

            return;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = $this->provisionAccount($email, $tenantDisplayName);
            if (! $user) {
                return;
            }
        }

        if (! $user->tenant_id) {
            $this->command->warn("User {$email} has no tenant_id. Skipping subscription.");

            return;
        }

        Subscription::updateOrCreate(
            ['tenant_id' => $user->tenant_id],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'interval' => 'monthly',
                'current_period_start' => now()->toDateString(),
                'current_period_ends_at' => now()->addMonth()->toDateString(),
                'gateway' => 'system',
            ]
        );

        $this->command->info(
            "Assigned {$email} to Corporate (1 month, expires ".now()->addMonth()->toDateString().').'
        );
    }

    private function provisionAccount(string $email, string $tenantDisplayName): ?User
    {
        $companyId = $this->generateUniqueTenantId($tenantDisplayName);
        $this->createTenantWithDatabase($companyId);

        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();

        $user = User::create([
            'name' => $tenantDisplayName,
            'email' => $email,
            'password' => Hash::make(env('TESTING_SEED_PASSWORD', 'password')),
            'tenant_id' => $companyId,
            'role_id' => $adminRole?->id,
        ]);

        if ($adminRole) {
            $user->assignRole('admin');
        }

        $this->command->info(
            "Provisioned {$email} (tenant {$companyId}). Password: env TESTING_SEED_PASSWORD or \"password\"."
        );

        return $user;
    }

    private function generateUniqueTenantId(string $tenantDisplayName): string
    {
        do {
            $id = Str::slug($tenantDisplayName).'_'.random_int(100, 999);
        } while (Tenant::where('id', $id)->exists());

        return $id;
    }
}
