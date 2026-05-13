<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestingAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->ensureProvisionedLifetimeAccount(
            'admin@fasttrade.my',
            'Fast Trade',
            'corporate'
        );

        $accounts = [
            'admin@hirix.ai' => 'corporate',
            'corporate@accounter.com' => 'corporate',
            'sme@accounter.com' => 'sme',
            'admin@fasttrade.my' => 'corporate',
        ];

        foreach ($accounts as $email => $planSlug) {
            $user = User::where('email', $email)->first();
            $plan = Plan::where('slug', $planSlug)->first();

            if (!$plan) {
                $this->command->error("Plan $planSlug not found for $email.");
                continue;
            }

            if ($user) {
                if (!$user->tenant_id) {
                    $this->command->warn("User $email has no tenant_id. Skipping.");
                    continue;
                }

                Subscription::updateOrCreate(
                    ['tenant_id' => $user->tenant_id],
                    [
                        'plan_id' => $plan->id,
                        'status' => 'active',
                        'interval' => 'lifetime',
                        'current_period_start' => now(),
                        'current_period_ends_at' => null, // No expiry
                        'gateway' => 'system'
                    ]
                );

                $this->command->info("Updated $email to " . ucfirst($planSlug) . " Lifetime Access.");
            } else {
                $this->command->warn("User not found: $email");
            }
        }
    }

    /**
     * Create tenant + admin user + lifetime subscription when the user row does not exist yet.
     * Mirrors {@see \App\Http\Controllers\Auth\RegisteredUserController::store} but assigns the given plan.
     * Login password: env TESTING_SEED_PASSWORD, or "password" if unset.
     */
    private function ensureProvisionedLifetimeAccount(string $email, string $tenantDisplayName, string $planSlug): void
    {
        if (User::where('email', $email)->exists()) {
            return;
        }

        $plan = Plan::where('slug', $planSlug)->first();
        if (! $plan) {
            $this->command->error("Plan {$planSlug} not found; cannot provision {$email}.");

            return;
        }

        $companyId = $this->generateUniqueTenantId($tenantDisplayName);
        $tenant = Tenant::create(['id' => $companyId]);

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

        Subscription::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'interval' => 'lifetime',
                'current_period_start' => now(),
                'current_period_ends_at' => null,
                'gateway' => 'system',
            ]
        );

        $this->command->info("Provisioned {$email} (tenant {$companyId}) with ".ucfirst($planSlug).' lifetime. Password: env TESTING_SEED_PASSWORD or "password".');
    }

    private function generateUniqueTenantId(string $tenantDisplayName): string
    {
        do {
            $id = Str::slug($tenantDisplayName).'_'.random_int(100, 999);
        } while (Tenant::where('id', $id)->exists());

        return $id;
    }
}
