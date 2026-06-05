<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the missing indexes to the central DB.
 *
 * The central audit_logs table currently has only PRIMARY (id), so any list
 * page or "actions performed by user" lookup full-scans the table. This
 * migration brings it in line with the tenant audit_logs schema:
 *  - (auditable_type, auditable_id) for "who touched this resource"
 *  - user_id for "what did this user do"
 *  - created_at for the default desc sort on listing pages.
 *
 * Plus a couple of small wins:
 *  - customer_audit_logs.created_at (sorted on listing).
 *  - subscriptions.tenant_id (already covered by composite, but this exposes
 *    it as a single-column probe which speeds up "find tenant's subs" calls).
 *
 * Every index is guarded with hasIndex() so the migration is safe to re-run.
 */
return new class extends Migration
{
    /** @var array<string, array<int, array{cols: array<int,string>, name: string}>> */
    private array $plan = [
        'audit_logs' => [
            ['cols' => ['auditable_type', 'auditable_id'], 'name' => 'audit_logs_auditable_index'],
            ['cols' => ['user_id'], 'name' => 'audit_logs_user_id_index'],
            ['cols' => ['created_at'], 'name' => 'audit_logs_created_at_index'],
        ],

        'customer_audit_logs' => [
            ['cols' => ['created_at'], 'name' => 'customer_audit_logs_created_at_index'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->plan as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $idx) {
                $missing = array_filter(
                    $idx['cols'],
                    fn ($col) => ! Schema::hasColumn($table, $col)
                );
                if ($missing) {
                    continue;
                }

                if ($this->indexExists($table, $idx['name'])) {
                    continue;
                }

                Schema::table($table, function (Blueprint $t) use ($idx) {
                    $t->index($idx['cols'], $idx['name']);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->plan as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $idx) {
                if (! $this->indexExists($table, $idx['name'])) {
                    continue;
                }

                Schema::table($table, function (Blueprint $t) use ($idx) {
                    $t->dropIndex($idx['name']);
                });
            }
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = DB::connection()->getDatabaseName();
        $rows = DB::select(
            'SELECT COUNT(*) AS c FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $indexName]
        );

        return ! empty($rows) && (int) $rows[0]->c > 0;
    }
};
