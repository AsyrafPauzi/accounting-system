<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Invoices
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete',
            'invoices.post', 'invoices.void', 'invoices.record-payment', 'invoices.email',

            // Bills
            'bills.view', 'bills.create', 'bills.edit', 'bills.delete',
            'bills.post', 'bills.void', 'bills.record-payment',

            // Customers
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
            'customers.edit-credit-risk',

            // Suppliers
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',

            // Credit Notes
            'credit-notes.view', 'credit-notes.create',

            // Products & Services (line-item catalogue)
            'products.view', 'products.create', 'products.edit', 'products.delete',

            // Estimates (quotations)
            'estimates.view', 'estimates.create', 'estimates.edit', 'estimates.delete', 'estimates.convert', 'estimates.email',

            // Recurring invoices (scheduled templates)
            'recurring-invoices.view', 'recurring-invoices.create', 'recurring-invoices.edit', 'recurring-invoices.delete', 'recurring-invoices.run',

            // Chart of Accounts
            'accounts.view', 'accounts.create', 'accounts.edit', 'accounts.delete',

            // Journals & Ledger
            'journal.view', 'journal.create', 'journal.edit', 'journal.post', 'journal.delete',
            'general-ledger.view',

            // Reports
            'reports.view', 'reports.profit-loss', 'reports.sales', 'reports.balance-sheet',
            'reports.cashflow', 'reports.aged-reports', 'reports.export.limited', 'reports.export.full',
            'reports.sales-tax', 'reports.customer-credits', 'reports.purchases-by-vendor',

            // Settings & Admin
            'settings.view', 'settings.edit',
            'admin.tenants', 'admin.plans', 'admin.users', 'admin.audit',
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.manage', 'permissions.manage',
            'dashboard.basic', 'dashboard.standard', 'dashboard.advanced',
            'audit-logs.view', 'integrations.view',
            'audit.view',

            // Practice (Accountant track) — gate access to /practice
            // and to firm-level billing / client linking. firm-staff
            // get the lighter set; firm-owner gets all of these.
            'practice.access',         // can open the Practice console at all
            'practice.clients.view',   // see the client list + client-level dashboards
            'practice.clients.invite', // create / send firm-to-client invites
            'practice.clients.unlink', // remove a client from the firm
            'practice.staff.manage',   // invite / remove firm-staff users
            'practice.billing.manage', // edit firm subscription, payment method
            'practice.reports.view',   // run cross-client reports
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Super Admin — every permission
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        // Admin — all except platform-operator permissions
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(
            Permission::whereNotIn('name', ['admin.tenants', 'admin.plans', 'admin.users', 'admin.audit'])->get()
        );

        // Accountant — full financial access, no admin management
        $accountant = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
        $accountant->syncPermissions([
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.post', 'invoices.void', 'invoices.record-payment', 'invoices.email', 'invoices.delete',
            'bills.view', 'bills.create', 'bills.edit', 'bills.post', 'bills.void', 'bills.record-payment', 'bills.delete',
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete', 'customers.edit-credit-risk',
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
            'credit-notes.view', 'credit-notes.create',
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'estimates.view', 'estimates.create', 'estimates.edit', 'estimates.delete', 'estimates.convert', 'estimates.email',
            'recurring-invoices.view', 'recurring-invoices.create', 'recurring-invoices.edit', 'recurring-invoices.delete', 'recurring-invoices.run',
            'accounts.view', 'accounts.create', 'accounts.edit', 'accounts.delete',
            'journal.view', 'journal.create', 'journal.edit', 'journal.post', 'journal.delete',
            'general-ledger.view',
            'reports.view', 'reports.profit-loss', 'reports.sales', 'reports.balance-sheet', 'reports.cashflow', 'reports.aged-reports', 'reports.export.full',
            'reports.sales-tax', 'reports.customer-credits', 'reports.purchases-by-vendor',
            'settings.view', 'audit-logs.view', 'integrations.view',
            'dashboard.basic', 'dashboard.standard', 'dashboard.advanced',
        ]);

        // Sales — invoices and customers only (full access), read-only on everything else
        $sales = Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);
        $sales->syncPermissions([
            // Full Sales Access
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.post', 'invoices.void', 'invoices.record-payment', 'invoices.email',
            'customers.view', 'customers.create', 'customers.edit',
            'credit-notes.view', 'credit-notes.create',
            'products.view', 'products.create', 'products.edit',
            'estimates.view', 'estimates.create', 'estimates.edit', 'estimates.delete', 'estimates.convert', 'estimates.email',
            'recurring-invoices.view', 'recurring-invoices.create', 'recurring-invoices.edit', 'recurring-invoices.run',
            
            // View Only Access on others
            'bills.view',
            'suppliers.view',
            'accounts.view',
            'journal.view',
            'general-ledger.view',
            'reports.view', 'reports.profit-loss', 'reports.sales',
            'settings.view',
            'dashboard.basic', 'dashboard.standard',
        ]);

        // Viewer — read-only everywhere
        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $viewer->syncPermissions([
            'invoices.view',
            'bills.view',
            'customers.view',
            'suppliers.view',
            'credit-notes.view',
            'products.view',
            'estimates.view',
            'recurring-invoices.view',
            'accounts.view',
            'journal.view',
            'general-ledger.view',
            'reports.view', 'reports.profit-loss', 'reports.sales', 'reports.balance-sheet', 'reports.cashflow', 'reports.aged-reports',
            'reports.sales-tax', 'reports.customer-credits', 'reports.purchases-by-vendor',
            'settings.view',
            'dashboard.basic',
        ]);

        // -----------------------------------------------------------
        // Practice (Accountant track) roles
        // -----------------------------------------------------------
        // These users live on the central DB and belong to a `firm`.
        // When they switch *into* a client, they pick up the
        // `accountant` role's permissions inside that tenant via the
        // FirmClient pivot — so we don't duplicate the financial
        // permissions here. These two roles only govern firm-level
        // privileges (Practice console + billing + client onboarding).

        // firm-owner — billing, client onboarding, staff management
        $firmOwner = Role::firstOrCreate(['name' => 'firm-owner', 'guard_name' => 'web']);
        $firmOwner->syncPermissions([
            'practice.access',
            'practice.clients.view',
            'practice.clients.invite',
            'practice.clients.unlink',
            'practice.staff.manage',
            'practice.billing.manage',
            'practice.reports.view',
            // The owner also gets a working profile / settings page
            // outside the console.
            'settings.view',
            'dashboard.basic',
        ]);

        // firm-staff — bookkeeping access only; no billing, no
        // client onboarding decisions.
        $firmStaff = Role::firstOrCreate(['name' => 'firm-staff', 'guard_name' => 'web']);
        $firmStaff->syncPermissions([
            'practice.access',
            'practice.clients.view',
            'practice.reports.view',
            'settings.view',
            'dashboard.basic',
        ]);
    }
}
