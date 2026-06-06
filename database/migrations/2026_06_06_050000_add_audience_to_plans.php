<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distinguishes plan tiers by who they're sold to:
 *
 *   sme       — the existing Startup / Solo / Growth / Corporate /
 *               Enterprise tiers shown to direct SME tenants. Default
 *               so existing rows pick this up automatically.
 *
 *   practice  — Practice (Accountant track) plans. Sold to firms, not
 *               tenants; subscription rows for these have `firm_id`
 *               populated and `tenant_id` null.
 *
 * Without this column the same `plans` table couldn't represent both
 * audiences without funny slug-based filtering everywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'audience')) {
                $table->string('audience', 16)->default('sme')->after('slug');
                $table->index('audience');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (Schema::hasColumn('plans', 'audience')) {
                $table->dropIndex(['audience']);
                $table->dropColumn('audience');
            }
        });
    }
};
