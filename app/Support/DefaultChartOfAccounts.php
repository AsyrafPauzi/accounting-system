<?php

namespace App\Support;

/**
 * Single source of truth for the default Chart of Accounts.
 *
 * Used by:
 *  - The tenant migration that pre-fills new tenants on first migrate
 *    (database/migrations/tenant/..._seed_default_chart_of_accounts.php).
 *  - ChartOfAccountsController::seedDefault() — for the manual "Seed
 *    default" admin button (kept as a backfill path for tenants that
 *    deleted their COA and want it back).
 *  - DemoTestAccountSeeder — so the demo tenant matches reality.
 *
 * Codes are aligned with what InvoiceService and BillService rely on
 * (1100 AR, 1200 Bank, 2100 Tax, 2110 AP, 4000 Revenue, 5000/6000
 * Expenses) so the journal-posting code keeps working out of the box.
 *
 * Tenants are free to rename, deactivate, or delete any of these rows —
 * deletion is permitted by ChartOfAccountsController unless the account
 * is referenced by a posted journal item.
 */
final class DefaultChartOfAccounts
{
    /**
     * @return list<array{code:string,name:string,type:string,sub_type?:string|null,display_order:int,description?:string}>
     */
    public static function rows(): array
    {
        return [
            // Assets
            ['code' => '1000', 'name' => 'Assets',              'type' => 'asset',     'display_order' => 1,  'description' => 'Top-level header for all asset accounts.'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset',     'display_order' => 2,  'description' => 'Outstanding amounts from customers (auto-posted by invoices).'],
            ['code' => '1200', 'name' => 'Bank',                'type' => 'asset',     'sub_type' => 'bank', 'display_order' => 3, 'description' => 'Default bank account — receives invoice payments and pays bills.'],
            ['code' => '1210', 'name' => 'Petty Cash',          'type' => 'asset',     'sub_type' => 'cash', 'display_order' => 4, 'description' => 'Cash on hand for small expenses.'],

            // Liabilities
            ['code' => '2000', 'name' => 'Liabilities',         'type' => 'liability', 'display_order' => 5,  'description' => 'Top-level header for all liability accounts.'],
            ['code' => '2100', 'name' => 'Tax Payable',         'type' => 'liability', 'display_order' => 6,  'description' => 'SST / output tax owed to LHDN.'],
            ['code' => '2110', 'name' => 'Accounts Payable',    'type' => 'liability', 'display_order' => 7,  'description' => 'Outstanding amounts owed to suppliers (auto-posted by bills).'],

            // Equity
            ['code' => '3000', 'name' => 'Equity',              'type' => 'equity',    'display_order' => 8,  'description' => 'Owner equity / retained earnings.'],

            // Income
            ['code' => '4000', 'name' => 'Revenue',             'type' => 'income',    'display_order' => 9,  'description' => 'Sales revenue (auto-posted by invoices).'],

            // Expenses
            ['code' => '5000', 'name' => 'Expenses',            'type' => 'expense',   'display_order' => 10, 'description' => 'Top-level expense bucket — used as the default account on bills if none chosen.'],
        ];
    }
}
