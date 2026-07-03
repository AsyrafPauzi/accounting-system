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
 *   Corporate    RM  219 / mo  — 5 users, payroll + audit (MyInvois soon)
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
        // Self-hosted installs don't read SaaS plan rows — entitlement
        // is license-driven and `CheckPlanPermission` short-circuits in
        // self-hosted mode. Standard installs (no Practice console)
        // never need them; we skip seeding to keep the DB lean and
        // reduce the surface area of a stolen DB dump.
        //
        // Enterprise self-hosted DOES seed them, because
        // `Practice\AddClientController::createNew()` attaches a
        // `startup` Subscription row to each new client tenant for
        // shape compatibility with SaaS exports — harmless but expects
        // the row to exist.
        if (\App\Support\Deployment::isSelfHosted() && ! \App\Support\Deployment::practiceConsoleEnabled()) {
            return;
        }

        $this->ensureAllPermissionsExist();

        // SME plans (sold to direct tenants)
        $startup    = $this->upsertStartup();
        $solo       = $this->upsertSolo();
        $growth     = $this->upsertGrowth();
        $corporate  = $this->upsertCorporate();
        $enterprise = $this->upsertEnterprise();

        // Practice plans (sold to accountancy firms — Phase G)
        $practiceFree       = $this->upsertPracticeFree();
        $practiceStarter    = $this->upsertPracticeStarter();
        $practiceGrowth     = $this->upsertPracticeGrowth();
        $practiceFirm       = $this->upsertPracticeFirm();
        // Self-hosted is sold as a "Talk to sales" tier — checkout refuses
        // it, the picker shows the card with a mailto, and procurement
        // happens out-of-band (license issued by hand).
        $practiceSelfHosted = $this->upsertPracticeSelfHosted();

        $this->deactivateLegacyPlans();

        $this->syncPermissions($startup, $solo, $growth, $corporate, $enterprise);
        $this->syncPracticePermissions($practiceFree, $practiceStarter, $practiceGrowth, $practiceFirm);
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
            'estimates.view', 'estimates.create', 'estimates.edit', 'estimates.delete', 'estimates.convert', 'estimates.email',
            'recurring-invoices.view', 'recurring-invoices.create', 'recurring-invoices.edit', 'recurring-invoices.delete', 'recurring-invoices.run',
            'accounts.view', 'accounts.create', 'accounts.edit', 'accounts.delete',
            'journal.view', 'journal.create', 'journal.edit', 'journal.delete', 'journal.post',
            'general-ledger.view',
            'reports.view', 'reports.profit-loss', 'reports.sales', 'reports.balance-sheet', 'reports.cashflow', 'reports.aged-reports', 'reports.export.limited', 'reports.export.full',
            'reports.sales-tax', 'reports.customer-credits', 'reports.purchases-by-vendor',
            'settings.view', 'settings.edit', 'audit-logs.view', 'integrations.view',
            'dashboard.basic', 'dashboard.standard', 'dashboard.advanced',
            // External API access gate (Solo+). Required to generate API
            // keys from Settings → Integrations and to call /api/v1.
            'api.access',
            // Tier-specific feature gates added June 2026 to align routes
            // with the bullets on the pricing page. See PlanPermissionAlignmentTest.
            'customer-statements.view',  // Growth+ ("Customer statements")
            // currencies.multi was dropped June 2026 — the multi-currency
            // feature ships to all tiers today (currency dropdown +
            // exchange-rate field on invoices/estimates) and was never
            // gated by a route check. Re-add the permission only if you
            // also wire the matching middleware on the invoice POST.
            'ocr.use',                   // Solo+   ("OCR receipt capture")
            'payroll.run',               // Corporate+ ("Payroll module")
            // Coming-soon Corporate / Enterprise features. The permission
            // exists so we can wire the future controller behind it without
            // a migration; today no route consumes it.
            'myinvois.submit',           // Corporate+ ("LHDN MyInvois e-Invoicing — coming soon")
            'sso.configure',             // Enterprise ("Single sign-on — coming soon")
            // Practice (Accountant track)
            'practice.access', 'practice.clients.view', 'practice.clients.invite',
            'practice.clients.unlink', 'practice.staff.manage',
            'practice.billing.manage', 'practice.reports.view',
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
                'audience' => 'sme',
                'price_monthly' => 0.00,
                'price_yearly'  => 0.00,
                'users_included' => 1,
                'extra_user_price' => 0.00,
                'features' => [
                    // Honest copy: Startup can issue invoices but not
                    // record payments (Solo+) and not OCR receipts
                    // (Solo+). Avoid "& receipts" so the marketing
                    // page doesn't promise something the UI can't deliver.
                    'Basic invoicing',
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
                    // "Bank-reconciliation reports" was historically
                    // listed here but the feature is on the roadmap,
                    // not in the system. Removed to keep marketing
                    // copy honest until reconciliation ships.
                    'Everything in Startup',
                    'Unlimited invoices, customers & bills',
                    'Email invoices & estimates',
                    'Recurring invoices & credit notes',
                    'OCR receipt capture',
                    'P&L and sales reports',
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
                    // Bullets reconciled June 2026 against actual
                    // shipping features:
                    //   - "& portal"          → removed (no customer-facing portal exists today; statements ship via PDF email).
                    //   - "Multi-currency invoicing" → removed (feature works but isn't plan-gated; not a real Growth differentiator).
                    //     Replaced with "Balance sheet & cash flow reports", which IS Growth-exclusive over Solo.
                    'Everything in Solo',
                    'Up to 3 team members included',
                    'Customer statements',
                    'Products & services catalogue',
                    'Balance sheet & cash flow reports',
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
                    'Audit log & compliance pack',
                    'Payroll module',
                    'LHDN MyInvois e-Invoicing — coming soon',
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
                    'White-label branding (self-hosted)',
                    'Single sign-on (SSO) — coming soon',
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

    // -----------------------------------------------------------------
    // Practice (Accountant track) plans
    // -----------------------------------------------------------------
    // Sold to firms managing multiple SME clients. Pricing follows
    // Xero Partner / QBO Accountant style: a fixed firm fee that
    // includes N client seats; firms above the limit step up to the
    // next tier rather than paying per-extra-client.
    //
    // Practice plans don't gate `practice.*` permissions — the
    // firm-owner / firm-staff *roles* already do that. The plan rows
    // exist so we can put firms on a paid Subscription row, charge
    // them via Toyyibpay, and gate billing-only features (cross-client
    // reports, advanced firm seats) by plan slug at the controller
    // layer when those are built.

    private function upsertPracticeFree(): Plan
    {
        // Top-of-funnel for accountants. One client, one firm-staff
        // seat, no expiry — generous enough that an accountant can
        // actually use it for their smallest client and decide if the
        // workflow fits before paying. Caps in the controller layer
        // enforce the "1 client" limit.
        return Plan::updateOrCreate(
            ['slug' => 'practice-free'],
            [
                'name' => 'Practice Free',
                'audience' => 'practice',
                'price_monthly' => 0.00,
                'price_yearly'  => 0.00,
                'users_included' => 1,
                'client_cap' => 1,
                'extra_user_price' => 0.00,
                'features' => [
                    'Manage 1 client (perfect for trying it out)',
                    '1 firm-staff seat',
                    'Practice console with cross-client overview',
                    'Switch into your client with one click',
                    'Community support',
                ],
                'is_active' => true,
                'is_contact_sales' => false,
            ]
        );
    }

    private function upsertPracticeStarter(): Plan
    {
        return Plan::updateOrCreate(
            ['slug' => 'practice-starter'],
            [
                'name' => 'Practice Starter',
                'audience' => 'practice',
                'price_monthly' => 99.00,
                'price_yearly'  => 990.00, // ~17% off
                'users_included' => 1,    // 1 firm staff seat
                'client_cap' => 5,
                'extra_user_price' => 25.00,
                'features' => [
                    'Manage up to 5 client books',
                    '1 firm-staff seat',
                    'Practice console with cross-client overview',
                    'Switch into any client with one click',
                    'Email support',
                ],
                'is_active' => true,
                'is_contact_sales' => false,
            ]
        );
    }

    private function upsertPracticeGrowth(): Plan
    {
        return Plan::updateOrCreate(
            ['slug' => 'practice-growth'],
            [
                'name' => 'Practice Growth',
                'audience' => 'practice',
                'price_monthly' => 249.00,
                'price_yearly'  => 2490.00,
                'users_included' => 3,
                'client_cap' => 25,
                'extra_user_price' => 25.00,
                'features' => [
                    'Manage up to 25 client books',
                    '3 firm-staff seats',
                    'Cross-client AR aging & SST reports',
                    'Bulk-action toolbar across clients',
                    'Priority email support',
                ],
                'is_active' => true,
                'is_contact_sales' => false,
            ]
        );
    }

    private function upsertPracticeFirm(): Plan
    {
        return Plan::updateOrCreate(
            ['slug' => 'practice-firm'],
            [
                'name' => 'Practice Firm',
                'audience' => 'practice',
                'price_monthly' => 599.00,
                'price_yearly'  => 5990.00,
                'users_included' => 10,
                'client_cap' => null, // unlimited
                'extra_user_price' => 35.00,
                'features' => [
                    'Manage unlimited client books',
                    '10 firm-staff seats included',
                    'Cross-client reports + custom branding',
                    'Practice-wide audit log & activity feed',
                    'Phone + email support during MY business hours',
                ],
                'is_active' => true,
                'is_contact_sales' => false,
            ]
        );
    }

    private function upsertPracticeSelfHosted(): Plan
    {
        // No price stored: the picker shows a "Talk to sales" CTA with a
        // mailto, and `PracticeBillingController::checkout()` refuses any
        // attempt to subscribe to a contact-sales plan — keeps the row
        // visible without letting it slip into the Toyyibpay flow.
        return Plan::updateOrCreate(
            ['slug' => 'practice-self-hosted'],
            [
                'name' => 'Practice Self-hosted',
                'audience' => 'practice',
                'price_monthly' => 0.00,
                'price_yearly'  => 0.00,
                'users_included' => 9999,
                'client_cap' => null, // unlimited
                'extra_user_price' => 0.00,
                'features' => [
                    'Run BukuCloud on your own infrastructure',
                    'Everything in Practice Firm',
                    'Custom SLAs & uptime guarantees',
                    'White-label branding',
                    'Single sign-on (SSO) — coming soon',
                    'Dedicated implementation engineer',
                ],
                'is_active' => true,
                'is_contact_sales' => true,
            ]
        );
    }

    private function syncPracticePermissions(Plan $free, Plan $starter, Plan $growth, Plan $firm): void
    {
        // All practice plans get the same surface area for now —
        // differentiation is operational (client cap, seats, support
        // tier) rather than feature-gated. We can split this up later
        // by gating cross-client reports to Growth+, etc.
        //
        // We deliberately don't sync permissions on practice-self-hosted:
        // it's a contact-sales placeholder, never reached by a real
        // subscription, so leaving it permission-less avoids implying
        // SaaS-tier capabilities for what is actually license-driven
        // entitlement on the customer's own server.
        $perms = [
            'practice.access',
            'practice.clients.view',
            'practice.clients.invite',
            'practice.clients.unlink',
            'practice.staff.manage',
            'practice.billing.manage',
            'practice.reports.view',
            'dashboard.basic',
        ];

        foreach ([$free, $starter, $growth, $firm] as $plan) {
            $plan->syncPermissions($perms);
        }
    }

    private function syncPermissions(Plan $startup, Plan $solo, Plan $growth, Plan $corporate, Plan $enterprise): void
    {
        // Tier surface area is layered Startup ⊂ Solo ⊂ Growth ⊂ Corporate.
        // Enterprise mirrors Corporate's surface and differentiates on
        // SLA / branding / SSO instead of feature gates.
        //
        // Each bullet on the pricing page must map to a permission in
        // ensureAllPermissionsExist() AND a `plan.permission:*` gate in
        // routes/web.php. PlanPermissionAlignmentTest enforces this.

        // ----- Startup (Free) -----
        // "Basic invoicing, up to 5 active customers, single bank
        // account, 1 user, community support".
        // Caps for "5 customers" and "1 bank account" are enforced by
        // PlanCap::guardCustomers / guardBankAccounts in the controllers,
        // not by Spatie permissions.
        $startup->syncPermissions([
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete', 'invoices.post', 'invoices.void',
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
            'accounts.view', // they need to see their seeded COA / bank account
            'settings.view', 'settings.edit', 'dashboard.basic',
        ]);

        // ----- Solo -----
        // Adds bills, suppliers, credit notes, recurring invoices,
        // estimates, OCR, P&L + sales reports, email-the-invoice.
        // Drops the Startup-only customer/bank caps (still 1 user).
        // Does NOT include products.* — that moved to Growth.
        $solo->syncPermissions([
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete', 'invoices.post', 'invoices.void', 'invoices.record-payment', 'invoices.email',
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
            'bills.view', 'bills.create', 'bills.edit', 'bills.delete', 'bills.post', 'bills.void', 'bills.record-payment',
            'credit-notes.view', 'credit-notes.create',
            'estimates.view', 'estimates.create', 'estimates.edit', 'estimates.delete', 'estimates.convert', 'estimates.email',
            'recurring-invoices.view', 'recurring-invoices.create', 'recurring-invoices.edit', 'recurring-invoices.delete', 'recurring-invoices.run',
            'ocr.use',
            'accounts.view',
            'general-ledger.view',
            'journal.view',
            'reports.view', 'reports.profit-loss', 'reports.sales', 'reports.export.limited',
            'settings.view', 'settings.edit',
            // Both dashboards on Solo+ — dashboard.basic for layered
            // inheritance from Startup, dashboard.standard for the
            // richer Solo-tier widgets.
            'dashboard.basic', 'dashboard.standard',
            // External API access (/api/v1/* + Settings → Integrations).
            // Solo is the lowest tier that gets it.
            'api.access', 'integrations.view',
        ]);

        // ----- Growth -----
        // Adds products catalogue, customer statements & portal,
        // multi-currency invoicing, sales-tax + ageing reports,
        // and a few CoA edit perms now that they're managing more books.
        $growth->syncPermissions(array_unique(array_merge(
            $solo->permissions->pluck('name')->all(),
            [
                'products.view', 'products.create', 'products.edit', 'products.delete',
                'customer-statements.view',
                'reports.sales-tax', 'reports.customer-credits', 'reports.purchases-by-vendor',
                'reports.balance-sheet', 'reports.cashflow', 'reports.aged-reports',
                'accounts.create', 'accounts.edit',
            ]
        )));

        // ----- Corporate -----
        // Full app surface (everything except super-admin / publisher
        // perms). Adds payroll, full export, audit log, MyInvois (coming
        // soon — permission exists, no controller yet).
        $corporate->syncPermissions(
            Permission::whereNotIn('name', [
                'admin.tenants', 'admin.plans', 'admin.users', 'admin.audit',
                'sso.configure', // Enterprise-only
            ])->where('guard_name', 'web')->get()
        );

        // ----- Enterprise -----
        // Same surface as Corporate plus SSO (coming soon — permission
        // exists, no controller yet). Operational differentiation
        // (SLAs, white-label branding [self-hosted only], dedicated
        // engineer) is contractual, not a permission gate.
        $enterprise->syncPermissions(array_unique(array_merge(
            $corporate->permissions->pluck('name')->all(),
            ['sso.configure']
        )));
    }
}
