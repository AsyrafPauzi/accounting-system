<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks whether a user has dismissed (or finished) the post-signup
 * onboarding modal. Null = show the modal on next dashboard visit.
 * A timestamp = they've seen it; never show again.
 *
 * We use a nullable timestamp instead of a boolean so we can later
 * answer "how long after signup did they actually finish onboarding?"
 * without a second column. Backfill is implicit — every existing user
 * gets `null` and will see the modal once on their next login.
 *
 * If you want to suppress the modal for users who registered before
 * this feature shipped, run a one-off:
 *
 *   App\Models\User::whereNull('welcomed_at')
 *       ->where('created_at', '<', '2026-06-09')
 *       ->update(['welcomed_at' => now()]);
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('welcomed_at')->nullable()->after('deletion_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('welcomed_at');
        });
    }
};
