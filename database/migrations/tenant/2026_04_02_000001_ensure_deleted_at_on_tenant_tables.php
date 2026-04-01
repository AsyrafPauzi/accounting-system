<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent: adds soft-delete column if missing (e.g. tenant DB created before
 * 2026_04_01_000001 or migrations not re-run via tenants:migrate).
 */
return new class extends Migration
{
    private array $tables = [
        'invoices',
        'invoice_items',
        'customers',
        'customer_contacts',
        'customer_audit_logs',
        'bills',
        'bill_items',
        'suppliers',
        'accounts',
        'credit_notes',
        'journal_entries',
        'journal_items',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
