<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (!Schema::hasColumn($table, 'uuid')) {
                    $blueprint->uuid('uuid')->nullable()->after('id');
                }
                if (!Schema::hasColumn($table, 'deleted_at')) {
                    $blueprint->softDeletes();
                }
            });

            // Backfill UUIDs for existing rows
            \DB::table($table)->whereNull('uuid')->orderBy('id')->chunk(500, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    \DB::table($table)->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
                }
            });
        }

        // Add unique index on uuid for all tables after backfill
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->unique('uuid', "idx_{$table}_uuid");
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropUnique("idx_{$table}_uuid");
                $blueprint->dropColumn(['uuid', 'deleted_at']);
            });
        }
    }
};
