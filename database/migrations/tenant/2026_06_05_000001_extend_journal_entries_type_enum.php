<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extend journal_entries.type enum so we can record bank/cash quick entries
 * (Wave-style "deposit" and "withdrawal") as their own kind, distinct from
 * generic manual journals or system-posted entries.
 *
 * Existing rows keep their value; only the column constraint widens.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE journal_entries MODIFY COLUMN type ENUM('manual','system','deposit','withdrawal') NOT NULL DEFAULT 'system'");
    }

    public function down(): void
    {
        // Roll back any deposit/withdrawal rows to 'manual' so the column can
        // shrink without violating the enum.
        DB::table('journal_entries')->whereIn('type', ['deposit', 'withdrawal'])->update(['type' => 'manual']);
        DB::statement("ALTER TABLE journal_entries MODIFY COLUMN type ENUM('manual','system') NOT NULL DEFAULT 'system'");
    }
};
