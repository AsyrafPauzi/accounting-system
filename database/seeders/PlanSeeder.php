<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure every permission referenced below exists in the central DB
        // BEFORE we try to attach them to plans. Otherwise Spatie throws
        // PermissionDoesNotExist on first run with new permissions.
        $allReferencedPermissions = [
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
        foreach ($allReferencedPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // 1. Startup (Free)
        $startup = Plan::updateOrCreate(
            ['slug' => 'startup'],
            [
                'name' => 'Startup (Free)',
                'price_monthly' => 0.00,
                'price_yearly' => 0.00,
                'users_included' => 1,
                'extra_user_price' => 0.00,
                'features' => [
                    'Full dashboard & reports',
                    'Basic invoicing',
                    '1 User included',
                    'Credit Notes',
                    'Customers Management',
                ],
                'is_active' => true,
            ]
        );

        $startup->syncPermissions([
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete', 'invoices.post', 'invoices.void',
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
            'credit-notes.view', 'credit-notes.create',
            'settings.view', 'settings.edit', 'dashboard.basic',
        ]);

        // 2. SME
        $sme = Plan::where('slug', 'sme')->first();
        if (!$sme) {
            $sme = Plan::where('slug', 'pro')->first();
        }

        if ($sme) {
            $sme->update([
                'name' => 'SME',
                'slug' => 'sme',
                'price_monthly' => 79.00,
                'price_yearly' => 853.00,
                'users_included' => 1,
                'extra_user_price' => 0.00,
                'features' => [
                    'Full dashboard & reports',
                    'Unlimited invoices & customers',
                    'Priority support',
                    '1 User included',
                    'Credit Notes',
                    'Payment Tracking',
                    'Email Invoices',
                ],
            ]);
        } else {
            $sme = Plan::create([
                'name' => 'SME',
                'slug' => 'sme',
                'price_monthly' => 79.00,
                'price_yearly' => 853.00,
                'users_included' => 1,
                'extra_user_price' => 0.00,
                'features' => [
                    'Full dashboard & reports',
                    'Unlimited invoices & customers',
                    'Priority support',
                    '1 User included',
                    'Credit Notes',
                    'Payment Tracking',
                    'Email Invoices',
                ],
                'is_active' => true,
            ]);
        }

        $smePermissions = [
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete', 'invoices.post', 'invoices.void', 'invoices.record-payment', 'invoices.email',
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
            'bills.view', 'bills.create', 'bills.edit', 'bills.delete', 'bills.post', 'bills.void', 'bills.record-payment',
            'credit-notes.view', 'credit-notes.create',
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'estimates.view', 'estimates.create', 'estimates.edit', 'estimates.delete', 'estimates.convert',
            'recurring-invoices.view', 'recurring-invoices.create', 'recurring-invoices.edit', 'recurring-invoices.delete', 'recurring-invoices.run',
            'accounts.view', // Chart of Accounts: Limited (View Only)
            'general-ledger.view', // General Ledger: View Only
            'journal.view', // Double Entry System: Partial (View Only journals)
            'reports.view', 'reports.profit-loss', 'reports.sales', 'reports.export.limited',
            'reports.sales-tax', 'reports.customer-credits', 'reports.purchases-by-vendor',
            'settings.view', 'settings.edit', 'dashboard.standard',
        ];

        $sme->syncPermissions($smePermissions);

        // Define all permissions if they don't exist
        $allPermissions = [
            'accounts.view', 'accounts.create', 'accounts.edit', 'accounts.delete',
            'journal.view', 'journal.create', 'journal.edit', 'journal.delete', 'journal.post',
            'general-ledger.view',
        ];

        foreach ($allPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // 3. Corporate
        $corporate = Plan::updateOrCreate(
            ['slug' => 'corporate'],
            [
                'name' => 'Corporate',
                'price_monthly' => 199.00,
                'price_yearly' => 2149.00,
                'users_included' => 3,
                'extra_user_price' => 15.00,
                'features' => [
                    'Full dashboard & reports',
                    'Unlimited invoices & customers',
                    'Dedicated account manager',
                    '3 Users included',
                    'Credit Notes',
                    'Payment Tracking',
                    'Email Invoices',
                    'Audit & Compliance',
                ],
                'is_active' => true,
            ]
        );

        $corporate->syncPermissions(
            Permission::whereNotIn('name', ['admin.tenants', 'admin.plans', 'admin.users', 'admin.audit'])->get()
        );
    }
}
