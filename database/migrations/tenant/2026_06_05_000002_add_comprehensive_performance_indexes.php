<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the second pass of performance indexes for tenant DBs.
 *
 * The first pass (2026_05_07_000001) covered status / date columns on the big
 * fact tables. This pass closes the remaining gaps surfaced by the audit:
 *  - foreign-key columns that were silently full-scanned (invoices.customer_id,
 *    bills.supplier_id) on customer/supplier-centric pages.
 *  - journal_entries.date / type / (reference_type, reference_id) which every
 *    accounting report and the transactions feed depends on.
 *  - account_code on journal_items + bill_items so account-based aggregations
 *    can stream the rows instead of scanning them.
 *  - composite (customer_id, status) and (status, due_date) indexes for the
 *    AR/AP outstanding + overdue dashboard widgets.
 *  - small-but-frequently-sorted columns (audit log created_at, customer/
 *    supplier names) so list pages stop sorting filesort-style.
 *
 * Every index is guarded with hasIndex() so the migration is safe to re-run
 * across tenants that may have been partially patched by hand.
 */
return new class extends Migration
{
    /**
     * Indexes to create per table.
     *
     * Format: ['table' => [['cols' => [...], 'name' => 'optional_index_name'], ...]]
     */
    private array $plan = [
        // High-impact: invoice queries.
        'invoices' => [
            ['cols' => ['customer_id'], 'name' => 'invoices_customer_id_index'],
            ['cols' => ['customer_id', 'status'], 'name' => 'invoices_customer_id_status_index'],
            ['cols' => ['status', 'due_date'], 'name' => 'invoices_status_due_date_index'],
        ],

        // High-impact: bill queries.
        'bills' => [
            ['cols' => ['supplier_id'], 'name' => 'bills_supplier_id_index'],
            ['cols' => ['supplier_id', 'status'], 'name' => 'bills_supplier_id_status_index'],
            ['cols' => ['status', 'due_date'], 'name' => 'bills_status_due_date_index'],
        ],

        // High-impact: every accounting report joins journal_entries by date and
        // sometimes by reference_type+reference_id (find journal for invoice X).
        'journal_entries' => [
            ['cols' => ['date'], 'name' => 'journal_entries_date_index'],
            ['cols' => ['type'], 'name' => 'journal_entries_type_index'],
            ['cols' => ['reference_type', 'reference_id'], 'name' => 'journal_entries_reference_index'],
            ['cols' => ['date', 'type'], 'name' => 'journal_entries_date_type_index'],
        ],

        // Aggregations (P&L, Balance Sheet, Trial Balance) join on account_code.
        'journal_items' => [
            ['cols' => ['account_code'], 'name' => 'journal_items_account_code_index'],
        ],

        // Purchases-by-vendor and expense-by-category reports.
        'bill_items' => [
            ['cols' => ['account_code'], 'name' => 'bill_items_account_code_index'],
        ],

        // Audit log lists are always ordered by created_at desc.
        'audit_logs' => [
            ['cols' => ['created_at'], 'name' => 'audit_logs_created_at_index'],
        ],

        'customer_audit_logs' => [
            ['cols' => ['created_at'], 'name' => 'customer_audit_logs_created_at_index'],
        ],

        // Customer/supplier directories are sorted by name; Wave-style search hits name too.
        'customers' => [
            ['cols' => ['name'], 'name' => 'customers_name_index'],
        ],

        'suppliers' => [
            ['cols' => ['name'], 'name' => 'suppliers_name_index'],
        ],

        // Billing-contact lookup is always (customer_id, type='billing'); reverse email lookup.
        'customer_contacts' => [
            ['cols' => ['customer_id', 'type'], 'name' => 'customer_contacts_customer_type_index'],
            ['cols' => ['email'], 'name' => 'customer_contacts_email_index'],
        ],

        // Credit notes are listed by date and filtered by (customer_id, status).
        'credit_notes' => [
            ['cols' => ['issue_date'], 'name' => 'credit_notes_issue_date_index'],
            ['cols' => ['customer_id', 'status'], 'name' => 'credit_notes_customer_status_index'],
        ],

        // Chart of accounts is filtered by (is_active=1, type=...).
        'accounts' => [
            ['cols' => ['is_active', 'type'], 'name' => 'accounts_is_active_type_index'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->plan as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $idx) {
                // Skip if any of the target columns are missing on this tenant.
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
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select(
                'SELECT COUNT(*) AS c FROM sqlite_master WHERE type = ? AND tbl_name = ? AND name = ?',
                ['index', $table, $indexName]
            );

            return ! empty($rows) && (int) $rows[0]->c > 0;
        }

        $database = DB::connection()->getDatabaseName();
        $rows = DB::select(
            'SELECT COUNT(*) AS c FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $indexName]
        );

        return ! empty($rows) && (int) $rows[0]->c > 0;
    }
};
