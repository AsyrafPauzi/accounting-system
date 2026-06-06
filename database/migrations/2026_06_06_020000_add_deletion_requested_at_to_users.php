<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a user (and by extension their owned tenant) as scheduled for
 * hard deletion. Set when the user confirms an erasure request from
 * Settings → Delete account; cleared if they cancel during the 30-day
 * cooling-off window. The retention:purge command picks up rows where
 * the timestamp is older than the cooling-off period and tears them
 * down for real.
 *
 * Why a timestamp, not a boolean flag:
 *   - We need the cooling-off window itself ("scheduled for deletion on
 *     YYYY-MM-DD") to show in the UI and the retention command.
 *   - Audit forensics later — "when did they ask?" is a real question
 *     in any subsequent PDPC inquiry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'deletion_requested_at')) {
                $table->timestamp('deletion_requested_at')->nullable()->after('data_exported_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'deletion_requested_at')) {
                $table->dropColumn('deletion_requested_at');
            }
        });
    }
};
