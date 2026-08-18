<?php

namespace App\Support;

use App\Models\Account;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent backfill for the default Chart of Accounts.
 *
 * Used by the tenant migration, login middleware, and the admin
 * "seed default" backfill action. Only inserts codes the tenant does
 * not already have — never overwrites customised rows.
 */
final class DefaultChartOfAccountsSeeder
{
    public static function seedMissing(): int
    {
        if (! Schema::hasTable('accounts')) {
            return 0;
        }

        $existingCodes = Account::query()->pluck('code')->all();
        $created = 0;

        foreach (DefaultChartOfAccounts::rows() as $row) {
            if (in_array($row['code'], $existingCodes, true)) {
                continue;
            }

            Account::create(array_merge($row, [
                'parent_id' => null,
                'is_active' => true,
            ]));
            $existingCodes[] = $row['code'];
            $created++;
        }

        return $created;
    }
}
