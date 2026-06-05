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

class TestingAccountsSeeder extends Seeder
{
    use ProvisionsTenantDatabase;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Idempotent provisioning for accounts that should always exist on a
        // freshly-seeded environment. Each one creates a tenant + admin user
        // + lifetime subscription if the email is new, and is a no-op if it
        // already exists.
        $this->ensureProvisionedLifetimeAccount(
            'admin@fasttrade.my',
            'Fast Trade',
            'corporate'
        );
        $this->ensureProvisionedLifetimeAccount(
            'growth@accounter.com',
            'Growth Demo',
            'growth'
        );

        // Plan slugs follow the June 2026 restructure (see PlanSeeder):
        //   - 'sme' is now inactive; existing sme@accounter.com tenant gets
        //     migrated to 'solo' (the closest successor — same 1-user cap,
        //     fully active and visible on the pricing page).
        //   - Add a 'growth' demo account so we have an end-to-end happy path
        //     for the new tier when manually testing.
        $accounts = [
            'admin@hirix.ai'           => 'corporate',
            'corporate@accounter.com'  => 'corporate',
            'sme@accounter.com'        => 'solo',
            'growth@accounter.com'     => 'growth',
            'admin@fasttrade.my'       => 'corporate',
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
        $tenant = $this->createTenantWithDatabase($companyId);

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

    /**
     * Pick a tenant id that's free in BOTH the central `tenants` table AND
     * MySQL's database catalogue. Past failed seeder runs sometimes leave a
     * tenant database behind without a matching `tenants` row, and the
     * Stancl provisioner refuses to recreate one if the database name is
     * already in use — checking only the central table would let us collide
     * with such an orphan and explode mid-seed.
     */
    private function generateUniqueTenantId(string $tenantDisplayName): string
    {
        do {
            $id = Str::slug($tenantDisplayName).'_'.random_int(100, 999);
        } while (Tenant::where('id', $id)->exists() || $this->tenantDatabaseExists($id));

        return $id;
    }

    private function tenantDatabaseExists(string $tenantId): bool
    {
        // Tenant database names follow Stancl's "tenant" + tenant_id pattern.
        // We use information_schema directly so we don't have to instantiate
        // a Tenant just to ask its DatabaseManager.
        $dbName = 'tenant'.$tenantId;
        return \Illuminate\Support\Facades\DB::connection(config('tenancy.database.central_connection', 'mysql'))
            ->selectOne(
                'SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1',
                [$dbName]
            ) !== null;
    }
}
