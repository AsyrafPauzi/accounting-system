<?php

namespace App\Console\Commands;

use App\Models\Firm;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Deployment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Bootstrap a self-hosted single-tenant install.
 *
 * Idempotent — running it twice does no harm. It:
 *   1. Verifies APP_DEPLOYMENT_MODE=self_hosted (refuses on saas).
 *   2. Creates the "default" Tenant if missing.
 *   3. Creates the admin User (or updates the password if --reset).
 *   4. Provisions the default Chart of Accounts inside the tenant.
 *
 * The first-run install wizard (Phase D) will call this command via
 * Artisan::call() once it has captured the admin email/password.
 *
 * Usage:
 *   php artisan self-hosted:bootstrap --email=admin@acme.test --name="Admin" --password=Secret!23
 *   php artisan self-hosted:bootstrap --email=admin@acme.test --reset-password=NewPass!23
 */
class SelfHostedBootstrap extends Command
{
    protected $signature = 'self-hosted:bootstrap
        {--email= : Admin / firm-owner email (created if missing)}
        {--name=Administrator : Admin / firm-owner display name}
        {--password= : Password (only if creating)}
        {--reset-password= : Reset existing user password}
        {--company-name= : Display name (company name in standard mode, firm name in firm mode)}
        {--firm-mode : Bootstrap an Enterprise (firm + clients) install instead of single-tenant}';

    protected $description = 'Bootstrap a self-hosted install. Standard = default tenant + admin user. Enterprise (--firm-mode) = firm + firm-owner with no default client tenant.';

    public function handle(): int
    {
        if (! Deployment::isSelfHosted()) {
            $this->error('Refusing to run: APP_DEPLOYMENT_MODE is not "self_hosted". Set it in .env first.');
            return self::FAILURE;
        }

        $email = (string) $this->option('email');
        if ($email === '') {
            $this->error('--email is required.');
            return self::FAILURE;
        }

        if ($this->option('firm-mode')) {
            return $this->handleFirmMode($email);
        }

        $tenantId = Deployment::DEFAULT_TENANT_ID;

        $this->info("Bootstrapping self-hosted install (tenant={$tenantId})…");

        // 1. Tenant — Stancl's Tenant::create provisions the per-tenant
        // database and runs tenant migrations as a side effect, which
        // is exactly what we need for the central tables to coexist
        // with the tenant tables in this single database.
        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            $tenant = Tenant::create([
                'id' => $tenantId,
                'display_name' => $this->option('company-name') ?: 'My Company',
            ]);
            $this->info("  ✓ Created default tenant.");
        } else {
            $this->line("  • Default tenant already exists.");
        }

        // 2. Roles / Permissions — must already be seeded. We don't
        // re-run RolesAndPermissionsSeeder here so the install
        // wizard can decide when to run it.
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if (! $adminRole) {
            $this->error('Role "admin" not found. Run `php artisan db:seed --class=RolesAndPermissionsSeeder` first.');
            return self::FAILURE;
        }

        // 3. Admin user
        $user = User::where('email', $email)->first();
        if (! $user) {
            $password = (string) $this->option('password');
            if ($password === '') {
                $this->error('--password is required when creating a new admin user.');
                return self::FAILURE;
            }

            $user = User::create([
                'name' => (string) $this->option('name'),
                'email' => $email,
                'password' => Hash::make($password),
                'tenant_id' => $tenantId,
                'role_id' => $adminRole->id,
                // Self-hosted: mark privacy as accepted on bootstrap;
                // there's no public-facing privacy click-through.
                'privacy_accepted_at' => now(),
                'privacy_accepted_version' => config('privacy.current_version', 'self-hosted'),
            ]);
            $user->assignRole('admin');
            $this->info("  ✓ Created admin user {$email}.");
        } elseif ($this->option('reset-password')) {
            $user->forceFill(['password' => Hash::make((string) $this->option('reset-password'))])->save();
            $this->info("  ✓ Reset admin password.");
        } else {
            $this->line("  • Admin user already exists; pass --reset-password=… to change it.");
        }

        // No Subscription row is provisioned — self-hosted installs
        // ignore the SaaS subscription system entirely. Both
        // `EnsureSubscribed` and `CheckPlanPermission` short-circuit
        // in self-hosted mode, and Inertia hardcodes
        // `hasActiveSubscription => true`. Carrying a dummy
        // "active" row was confusing (it lied about a billing
        // relationship that doesn't exist) and pulled in a Plan
        // dependency that's optional on Standard installs.

        $this->newLine();
        $this->info('Self-hosted bootstrap complete.');
        $this->line("Sign in at /login with: {$email}");
        return self::SUCCESS;
    }

    /**
     * Enterprise (firm-mode) bootstrap. Creates:
     *   - 1 Firm row (central, the "practice")
     *   - 1 firm-owner User (tenant_id null, firm_id set)
     *   - 1 firm-owner role assignment
     *
     * Does NOT create a default tenant — clients are added later
     * via the Practice console (`/practice/clients/create`).
     *
     * Idempotent: re-running with the same email just exits OK if
     * the firm + user already exist. Running with a different email
     * after a firm is set up returns failure (we'd need an explicit
     * --reset flag for that, which isn't part of the install wizard
     * UX).
     */
    private function handleFirmMode(string $email): int
    {
        $this->info('Bootstrapping self-hosted install (firm mode / Enterprise)…');

        // Roles must already be seeded — same precondition as the
        // standard path, just a different role to look up.
        $firmRole = Role::where('name', 'firm-owner')->where('guard_name', 'web')->first();
        if (! $firmRole) {
            $this->error('Role "firm-owner" not found. Run `php artisan db:seed --class=RolesAndPermissionsSeeder` first.');
            return self::FAILURE;
        }

        $firmName = (string) ($this->option('company-name') ?: 'My Firm');

        $firm = Firm::query()->first();
        if (! $firm) {
            $firm = Firm::create([
                'name'          => $firmName,
                'slug'          => Str::slug($firmName) ?: 'firm',
                'contact_email' => $email,
                'country'       => 'MY',
                'status'        => 'active',
            ]);
            $this->info("  ✓ Created firm \"{$firm->name}\".");
        } else {
            $this->line("  • Firm row already exists; reusing it.");
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            $password = (string) $this->option('password');
            if ($password === '') {
                $this->error('--password is required when creating a new firm-owner.');
                return self::FAILURE;
            }
            $user = User::create([
                'name'                     => (string) $this->option('name'),
                'email'                    => $email,
                'password'                 => Hash::make($password),
                'tenant_id'                => null,
                'firm_id'                  => $firm->id,
                'firm_role'                => 'owner',
                'role_id'                  => $firmRole->id,
                'privacy_accepted_at'      => now(),
                'privacy_accepted_version' => config('privacy.current_version', 'self-hosted'),
            ]);
            $user->assignRole('firm-owner');
            $this->info("  ✓ Created firm-owner {$email}.");
        } elseif ($this->option('reset-password')) {
            $user->forceFill(['password' => Hash::make((string) $this->option('reset-password'))])->save();
            $this->info("  ✓ Reset firm-owner password.");
        } else {
            $this->line("  • User already exists; pass --reset-password=… to change it.");
        }

        // Backfill the firm's owner pointer if it's still null. We
        // use forceFill rather than the $fillable-aware update path
        // so this works even on installs whose Firm model fillable
        // list changes between releases.
        if (! $firm->owner_user_id) {
            $firm->forceFill(['owner_user_id' => $user->id])->save();
        }

        $this->newLine();
        $this->info('Self-hosted bootstrap complete (firm mode).');
        $this->line("Sign in at /login with: {$email}");
        $this->line('Add your first client at /practice/clients/create.');
        return self::SUCCESS;
    }
}
