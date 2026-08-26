<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('journal_entries')
            ->where('type', 'system')
            ->where('status', 'draft')
            ->update(['status' => 'posted']);
    }

    public function down(): void
    {
        // Irreversible — posted system journals must stay posted.
    }
};
