<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records the last time a user requested a "Download my data" PDPA export.
 * Used as a rate-limit signal — we cap exports at 1 per 24 hours per user
 * to stop scrapers from periodically pulling everything via a compromised
 * session, and to keep the bandwidth/CPU cost of building the zip in
 * check.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'data_exported_at')) {
                $table->timestamp('data_exported_at')->nullable()->after('privacy_accepted_version');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'data_exported_at')) {
                $table->dropColumn('data_exported_at');
            }
        });
    }
};
