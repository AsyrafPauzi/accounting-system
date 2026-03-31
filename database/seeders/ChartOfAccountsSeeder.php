<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset'],
            ['code' => '1200', 'name' => 'Cash at Bank', 'type' => 'asset'],
            ['code' => '2100', 'name' => 'SST Payable', 'type' => 'liability'],
            ['code' => '2110', 'name' => 'Accounts Payable', 'type' => 'liability'],
            ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'income'],
            ['code' => '6000', 'name' => 'Operating Expenses', 'type' => 'expense'],
        ];

        foreach ($accounts as $account) {
            DB::table('accounts')->updateOrInsert(['code' => $account['code']], $account);
        }
    }
}