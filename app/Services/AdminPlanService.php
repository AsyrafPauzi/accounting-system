<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Plan;

class AdminPlanService
{
    public function create(array $validated): Plan
    {
        $permissions = $validated['permissions'] ?? [];
        unset($validated['permissions']);

        $validated['is_active'] = $validated['is_active'] ?? true;

        $plan = Plan::create($validated);
        $plan->syncPermissions($permissions);

        return $plan;
    }

    public function update(Plan $plan, array $validated): Plan
    {
        $permissions = $validated['permissions'] ?? [];
        unset($validated['permissions']);

        // Slug is immutable after creation — never update it.
        unset($validated['slug']);

        $plan->update($validated);
        $plan->syncPermissions($permissions);

        return $plan->refresh();
    }

    /**
     * Deactivate a plan instead of deleting it when active subscriptions exist.
     */
    public function deactivate(Plan $plan): Plan
    {
        $plan->update(['is_active' => false]);

        return $plan->refresh();
    }

    /**
     * All permissions grouped by resource for the frontend checkbox matrix.
     */
    public function availablePermissionsGrouped(): array
    {
        $groups = [
            'Invoices'          => ['invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete', 'invoices.post', 'invoices.void', 'invoices.record-payment', 'invoices.email'],
            'Bills'             => ['bills.view', 'bills.create', 'bills.edit', 'bills.delete', 'bills.post', 'bills.void', 'bills.record-payment'],
            'Customers'         => ['customers.view', 'customers.create', 'customers.edit', 'customers.delete', 'customers.edit-credit-risk'],
            'Suppliers'         => ['suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete'],
            'Credit Notes'      => ['credit-notes.view', 'credit-notes.create'],
            'Accounts'          => ['accounts.view', 'accounts.create', 'accounts.edit', 'accounts.delete'],
            'Journals & Ledger' => ['journal.view', 'journal.create', 'journal.edit', 'journal.post', 'journal.delete', 'general-ledger.view'],
            'Reports'           => ['reports.view', 'reports.profit-loss', 'reports.sales', 'reports.balance-sheet', 'reports.cashflow', 'reports.aged-reports', 'reports.export.limited', 'reports.export.full'],
            'Settings'          => ['settings.view', 'settings.edit'],
            'Team'              => ['users.view', 'users.create', 'users.edit', 'users.delete', 'roles.manage', 'permissions.manage'],
            'Dashboard'         => ['dashboard.basic', 'dashboard.standard', 'dashboard.advanced'],
            'Audit & Compliance' => ['audit-logs.view', 'audit.view'],
            'Integrations'      => ['integrations.view'],
        ];

        // Only return permissions that actually exist in the DB.
        $existing = Permission::whereNotIn('name', ['admin.tenants', 'admin.plans', 'admin.users', 'admin.audit'])
            ->pluck('name')
            ->flip()
            ->toArray();

        $filtered = [];
        foreach ($groups as $group => $perms) {
            $available = array_values(array_filter($perms, fn ($p) => isset($existing[$p])));
            if ($available) {
                $filtered[$group] = $available;
            }
        }

        return $filtered;
    }
}
