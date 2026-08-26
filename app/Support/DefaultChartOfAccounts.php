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
     * Most-used accounts that many SMEs need immediately.
     *
     * @return list<array{code:string,name:string,type:string,sub_type?:string|null,display_order:int,description?:string}>
     */
    public static function essentialRows(): array
    {
        $essentialCodes = [
            '1100', // Accounts Receivable
            '1110', // Tax Receivable
            '1200', // Bank
            '1210', // Petty Cash
            '1220', // Undeposited Funds
            '1300', // Supplier Prepayments
            '1310', // Staff Advances
            '2100', // Tax Payable
            '2110', // Accounts Payable
            '2250', // Customer Deposits
            '2300', // Director Current Account
            '3000', // Equity
            '3100', // Retained Earnings
            '3200', // Owner Drawings
            '4000', // Revenue
            '4100', // Other Income
            '5000', // Expenses
            '5100', // Salaries and Wages
            '5200', // Rent and Office
            '5300', // Utilities
            '5400', // Travel and Transport
            '5500', // Professional Fees
            '5600', // Software and Subscriptions
            '5700', // Marketing and Advertising
            '5800', // Repairs and Maintenance
            '5900', // Staff Welfare and Claims
        ];

        return array_values(array_filter(
            self::rows(),
            fn (array $row) => in_array($row['code'], $essentialCodes, true)
        ));
    }

    /**
     * @return list<array{code:string,name:string,type:string,sub_type?:string|null,display_order:int,description?:string}>
     */
    public static function rows(): array
    {
        return [
            // Assets
            ['code' => '1000', 'name' => 'Assets',                'type' => 'asset',     'display_order' => 1,  'description' => 'Top-level header for all asset accounts.'],
            ['code' => '1100', 'name' => 'Accounts Receivable',   'type' => 'asset',     'display_order' => 2,  'description' => 'Outstanding amounts from customers (auto-posted by invoices).'],
            ['code' => '1110', 'name' => 'Tax Receivable',        'type' => 'asset',     'display_order' => 3,  'description' => 'Input tax / recoverable SST or VAT paid on purchases.'],
            ['code' => '1200', 'name' => 'Bank',                  'type' => 'asset',     'sub_type' => 'bank', 'display_order' => 4, 'description' => 'Default bank account — receives invoice payments and pays bills.'],
            ['code' => '1210', 'name' => 'Petty Cash',            'type' => 'asset',     'sub_type' => 'cash', 'display_order' => 5, 'description' => 'Cash on hand for small expenses.'],
            ['code' => '1220', 'name' => 'Undeposited Funds',     'type' => 'asset',     'display_order' => 6,  'description' => 'Money received but not yet deposited into the bank.'],
            ['code' => '1300', 'name' => 'Supplier Prepayments',  'type' => 'asset',     'display_order' => 7,  'description' => 'Deposits paid to suppliers waiting to knock off bills.'],
            ['code' => '1310', 'name' => 'Staff Advances',        'type' => 'asset',     'display_order' => 8,  'description' => 'Temporary advances given to employees or team members.'],
            ['code' => '1400', 'name' => 'Inventory',             'type' => 'asset',     'display_order' => 9,  'description' => 'Stock on hand at weighted-average cost.'],
            ['code' => '1500', 'name' => 'Property, Plant & Equipment', 'type' => 'asset', 'display_order' => 10, 'description' => 'Fixed assets at cost.'],
            ['code' => '1510', 'name' => 'Accumulated Depreciation', 'type' => 'asset',  'display_order' => 11, 'description' => 'Contra-asset — accumulated depreciation on PPE.'],

            // Liabilities
            ['code' => '2000', 'name' => 'Liabilities',           'type' => 'liability', 'display_order' => 20, 'description' => 'Top-level header for all liability accounts.'],
            ['code' => '2100', 'name' => 'Tax Payable',           'type' => 'liability', 'display_order' => 21, 'description' => 'SST / output tax owed to LHDN.'],
            ['code' => '2110', 'name' => 'Accounts Payable',      'type' => 'liability', 'display_order' => 22, 'description' => 'Outstanding amounts owed to suppliers (auto-posted by bills).'],
            ['code' => '2250', 'name' => 'Customer Deposits',     'type' => 'liability', 'display_order' => 23, 'description' => 'Unapplied customer deposits waiting to knock off invoices.'],
            ['code' => '2300', 'name' => 'Director Current Account', 'type' => 'liability', 'display_order' => 24, 'description' => 'Amounts the company owes a director for claims, reimbursements, or personal payments made on behalf of the business.'],
            ['code' => '2310', 'name' => 'Loan From Director',    'type' => 'liability', 'display_order' => 25, 'description' => 'Formal loan balances due back to a director or owner.'],

            // Equity
            ['code' => '3000', 'name' => 'Equity',                'type' => 'equity',    'display_order' => 30, 'description' => 'Owner equity / retained earnings.'],
            ['code' => '3100', 'name' => 'Retained Earnings',     'type' => 'equity',    'display_order' => 31, 'description' => 'Accumulated profits kept in the business.'],
            ['code' => '3200', 'name' => 'Owner Drawings',        'type' => 'equity',    'display_order' => 32, 'description' => 'Money withdrawn by the owner or director for personal use.'],

            // Income
            ['code' => '4000', 'name' => 'Revenue',               'type' => 'income',    'display_order' => 40, 'description' => 'Sales revenue (auto-posted by invoices).'],
            ['code' => '4100', 'name' => 'Other Income',          'type' => 'income',    'display_order' => 41, 'description' => 'Non-core income such as rebates, commission, or miscellaneous gains.'],
            ['code' => '4200', 'name' => 'Foreign Exchange Gain', 'type' => 'income',    'display_order' => 42, 'description' => 'Realized gain when settling foreign-currency AR/AP at a favourable rate.'],

            // Expenses
            ['code' => '5000', 'name' => 'Expenses',              'type' => 'expense',   'display_order' => 50, 'description' => 'Top-level expense bucket — used as the default account on bills if none chosen.'],
            ['code' => '5010', 'name' => 'Cost of Goods Sold',    'type' => 'expense',   'display_order' => 51, 'description' => 'Inventory cost relieved on sales (auto-posted when products track stock).'],
            ['code' => '5100', 'name' => 'Salaries and Wages',    'type' => 'expense',   'display_order' => 52, 'description' => 'Payroll, wages, and fixed staff compensation.'],
            ['code' => '5200', 'name' => 'Rent and Office',       'type' => 'expense',   'display_order' => 53, 'description' => 'Office rent, workspace charges, and shared premises costs.'],
            ['code' => '5300', 'name' => 'Utilities',             'type' => 'expense',   'display_order' => 54, 'description' => 'Electricity, water, internet, and phone bills.'],
            ['code' => '5400', 'name' => 'Travel and Transport',  'type' => 'expense',   'display_order' => 55, 'description' => 'Taxi, mileage, toll, parking, flights, and travel claims.'],
            ['code' => '5500', 'name' => 'Professional Fees',     'type' => 'expense',   'display_order' => 56, 'description' => 'Accounting, legal, secretarial, and consulting fees.'],
            ['code' => '5600', 'name' => 'Software and Subscriptions', 'type' => 'expense', 'display_order' => 57, 'description' => 'SaaS tools, licenses, and recurring subscriptions.'],
            ['code' => '5700', 'name' => 'Marketing and Advertising', 'type' => 'expense', 'display_order' => 58, 'description' => 'Ads, promotions, and marketing campaign spend.'],
            ['code' => '5800', 'name' => 'Repairs and Maintenance', 'type' => 'expense', 'display_order' => 59, 'description' => 'Equipment repairs, upkeep, and maintenance services.'],
            ['code' => '5810', 'name' => 'Depreciation Expense',  'type' => 'expense',   'display_order' => 60, 'description' => 'Monthly straight-line depreciation on fixed assets.'],
            ['code' => '5900', 'name' => 'Staff Welfare and Claims', 'type' => 'expense', 'display_order' => 61, 'description' => 'Meals, minor reimbursements, and staff welfare costs.'],
            ['code' => '4300', 'name' => 'Foreign Exchange Loss', 'type' => 'expense',   'display_order' => 62, 'description' => 'Realized loss when settling foreign-currency AR/AP at an unfavourable rate.'],
        ];
    }
}
