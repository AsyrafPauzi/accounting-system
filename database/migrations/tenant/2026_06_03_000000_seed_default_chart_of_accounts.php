<?php

use App\Support\DefaultChartOfAccounts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-fill every tenant's Chart of Accounts so a fresh signup can issue
 * invoices and pay bills without first clicking "Seed default chart".
 *
 * Behaviour:
 *  - Runs as a tenant migration, so it fires automatically the moment a
 *    new tenant database is provisioned (registration, seeders, the
 *    `tenants:migrate` artisan command).
 *  - Idempotent: only inserts a row if the `code` is missing. A tenant
 *    that has already deleted or customised an account is left alone —
 *    we never overwrite their data.
 *  - Existing tenants get backfilled on the next `tenants:migrate` run,
 *    but again only for codes they don't already have.
 *
 * Tenants remain free to delete pre-filled rows via the Chart of
 * Accounts UI; the destroy controller permits deletion as long as the
 * account hasn't been referenced by a posted journal item.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounts')) {
            return;
        }

        $existingCodes = DB::table('accounts')->pluck('code')->all();
        $now = now();

        $rowsToInsert = [];
        foreach (DefaultChartOfAccounts::rows() as $row) {
            if (in_array($row['code'], $existingCodes, true)) {
                continue;
            }

            $rowsToInsert[] = [
                'code'          => $row['code'],
                'name'          => $row['name'],
                'type'          => $row['type'],
                'sub_type'      => $row['sub_type'] ?? null,
                'parent_id'     => null,
                'description'   => $row['description'] ?? null,
                'is_active'     => true,
                'display_order' => $row['display_order'],
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        if ($rowsToInsert !== []) {
            DB::table('accounts')->insert($rowsToInsert);
        }
    }

    public function down(): void
    {
        // Intentionally a no-op. Reversing this migration would risk
        // deleting accounts that the tenant has since used in postings.
        // If a tenant truly wants a clean slate, they can do that from
        // the Chart of Accounts UI on a per-row basis.
    }
};
