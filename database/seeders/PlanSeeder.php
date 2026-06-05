<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Plan tiers (June 2026 restructure):
 *
 *   Startup      RM    0       — 1 user, free tier (top of funnel)
 *   Solo         RM   49 / mo  — 1 user, replaces the old SME plan
 *   Growth       RM   99 / mo  — 3 users, fills the gap between Solo & Corp
 *   Corporate    RM  219 / mo  — 5 users, MyInvois + audit + approvals
 *   Enterprise   contact sales — unlimited users, self-hosted negotiated
 *
 * Yearly pricing is set ~17 % below 12× monthly (≈ "2 months free") which
 * matches the SaaS norm and is more aggressive than the previous 10 %.
 *
 * Backwards compatibility: the legacy `sme` plan is kept as inactive so any
 * tenant already subscribed to it stays grandfathered. New customers won't
 * see it on the pricing page (the controller filters by is_active).
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensureAllPermissionsExist();

        $startup    = $this->upsertStartup();
        $solo       = $this->upsertSolo();
        $growth     = $this->upsertGrowth();
        $corporate  = $this->upsertCorporate();
        $enterprise = $this->upsertEnterprise();

        $this->deactivateLegacyPlans();

        $this->syncPermissions($startup, $solo, $growth, $corporate, $enterprise);
    }

    /**
     * Create every permission referenced by a plan up front. Spatie throws
     * PermissionDoesNotExist if you call syncPermissions() with a name that
     * hasn't been created yet — this avoids that on a clean install.
     */
    private function ensureAllPermissionsExist(): void
    {
        $names = [
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete', 'invoices.post', 'invoices.void', 'invoices.record-payment', 'invoices.email',
            'bills.view', 'bills.create', 'bills.edit', 'bills.delete', 'bills.post', 'bills.void', 'bills.record-payment',
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
            'credit-notes.view', 'credit-notes.create',
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'estimates.view', 'estimates.create', 'estimates.edit', 'estimates.delete', 'estimates.convert',
            'recurring-invoices.view', 'recurring-invoices.create', 'recurring-invoices.edit', 'recurring-invoices.delete', 'recurring-invoices.run',
            'accounts.view', 'accounts.create', 'accounts.edit', 'accounts.delete',
            'journal.view', 'journal.create', 'journal.edit', 'journal.delete', 'journal.post',
            'general-ledger.view',
            'reports.view', 'reports.profit-loss', 'reports.sales', 'reports.balance-sheet', 'reports.cashflow', 'reports.aged-reports', 'reports.export.limited', 'reports.export.full',
            'reports.sales-tax', 'reports.customer-credits', 'reports.purchases-by-vendor',
            'settings.view', 'settings.edit', 'audit-logs.view', 'integrations.view',
            'dashboard.basic', 'dashboard.standard', 'dashboard.advanced',
        ];

        foreach ($names as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
    }

    private function upsertStartup(): Plan
    {
        return Plan::updateOrCreate(
            ['slug' => 'startup'],
            [
                'name' => 'Startup (Free)',
                'price_monthly' => 0.00,
                'price_yearly'  => 0.00,
                'users_included' => 1,
                'extra_user_price' => 0.00,
                'features' => [
                    'Basic invoicing & receipts',
                    'Up to 5 active customers',
                    'Single bank account',
                    '1 user',
                    'Community support',
                ],
                'is_active' => true,
                'is_contact_sales' => false,
            ]
        );
    }

    private function upsertSolo(): Plan
    {
        return Plan::updateOrCreate(
            ['slug' => 'solo'],
            [
                'name' => 'Solo',
                'price_monthly' => 49.00,
                'price_yearly'  => 490.00, // ~17% off
                'users_included' => 1,
                'extra_user_price' => 0.00,
                'features' => [
                    'Everything in Startup',
                    'Unlimited invoices, customers & bills',
                    'Email invoices & estimates',
                    'Recurring invoices & credit notes',
                    'OCR receipt capture',
                    'Bank-reconciliation reports',
                    '1 user (no extras)',
                    'Email support',
                ],
                'is_active' => true,
                'is_contact_sales' => false,
            ]
        );
    }

    private function upsertGrowth(): Plan
    {
        return Plan::updateOrCreate(
            ['slug' => 'growth'],
            [
                'name' => 'Growth',
                'price_monthly' => 99.00,
                'price_yearly'  => 990.00,
                'users_included' => 3,
                'extra_user_price' => 12.00,
                'features' => [
                    'Everything in Solo',
                    'Up to 3 team members included',
                    'Customer statements & portal',
                    'Products & services catalogue',
                    'Multi-currency invoicing',
                    'Sales tax & ageing reports',
                    'Priority email support',
                ],
                'is_active' => true,
                'is_contact_sales' => false,
            ]
        );
    }

    private function upsertCorporate(): Plan
    {
        return Plan::updateOrCreate(
            ['slug' => 'corporate'],
            [
                'name' => 'Corporate',
                'price_monthly' => 219.00,
                'price_yearly'  => 2190.00,
                'users_included' => 5,
                'extra_user_price' => 15.00,
                'features' => [
                    'Everything in Growth',
                    'Up to 5 team members included',
                    'Approval workflows for bills',
                    'Audit log & compliance pack',
                    'LHDN MyInvois e-Invoicing*',
                    'Payroll module',
                    'Dedicated account manager',
                ],
                'is_active' => true,
                'is_contact_sales' => false,
            ]
        );
    }

    private function upsertEnterprise(): Plan
    {
        // No price stored: the UI shows "Talk to us", and the checkout
        // endpoint refuses any attempt to subscribe to a contact-sales plan.
        return Plan::updateOrCreate(
            ['slug' => 'enterprise'],
            [
                'name' => 'Enterprise',
                'price_monthly' => 0.00,
                'price_yearly'  => 0.00,
                'users_included' => 9999, // effectively unlimited; UI shows "Unlimited"
                'extra_user_price' => 0.00,
                'features' => [
                    'Everything in Corporate',
                    'Unlimited team members',
                    'Self-hosted deployment option',
                    'Custom SLAs & uptime guarantees',
                    'White-label branding',
                    'Single sign-on (SSO)',
                    'Custom integrations & data residency',
                    'Dedicated implementation engineer',
                ],
                'is_active' => true,
                'is_contact_sales' => true,
            ]
        );
    }

    /**
     * Tenants currently subscribed to the legacy `sme` plan keep their
     * subscription (the row stays in the central DB), but we hide it from
     * the public pricing page by flipping is_active off.
     */
    private function deactivateLegacyPlans(): void
    {
        Plan::whereIn('slug', ['sme', 'pro'])->update(['is_active' => false]);
    }

    private function syncPermissions(Plan $startup, Plan $solo, Plan $growth, Plan $corporate, Plan $enterprise): void
    {
        $startup->syncPermissions([
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete', 'invoices.post', 'invoices.void',
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
            'credit-notes.view', 'credit-notes.create',
            'settings.view', 'settings.edit', 'dashboard.basic',
        ]);

        $solo->syncPermissions([
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete', 'invoices.post', 'invoices.void', 'invoices.record-payment', 'invoices.email',
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
            'bills.view', 'bills.create', 'bills.edit', 'bills.delete', 'bills.post', 'bills.void', 'bills.record-payment',
            'credit-notes.view', 'credit-notes.create',
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'estimates.view', 'estimates.create', 'estimates.edit', 'estimates.delete', 'estimates.convert',
            'recurring-invoices.view', 'recurring-invoices.create', 'recurring-invoices.edit', 'recurring-invoices.delete', 'recurring-invoices.run',
            'accounts.view',
            'general-ledger.view',
            'journal.view',
            'reports.view', 'reports.profit-loss', 'reports.sales', 'reports.export.limited',
            'settings.view', 'settings.edit', 'dashboard.standard',
        ]);

        // Growth = Solo + a few extra reports + multi-currency-style perms.
        // We model that as Solo's permissions plus the strategic ones.
        $growth->syncPermissions(array_unique(array_merge(
            $solo->permissions->pluck('name')->all(),
            [
                'reports.sales-tax', 'reports.customer-credits', 'reports.purchases-by-vendor',
                'reports.balance-sheet', 'reports.cashflow', 'reports.aged-reports',
                'accounts.create', 'accounts.edit',
            ]
        )));

        // Corporate = full access except admin-only super-admin permissions.
        $corporate->syncPermissions(
            Permission::whereNotIn('name', ['admin.tenants', 'admin.plans', 'admin.users', 'admin.audit'])
                ->where('guard_name', 'web')
                ->get()
        );

        // Enterprise inherits Corporate's permission set — they get the same
        // app surface, the differentiation is operational (SLAs, SSO,
        // self-hosting) not a permission gate.
        $enterprise->syncPermissions($corporate->permissions->pluck('name')->all());
    }
}
