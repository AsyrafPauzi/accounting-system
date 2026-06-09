<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks when the user last clicked "Skip for now" on the email
 * verification reminder modal. Null = the reminder has not been
 * dismissed yet (so the modal will show on next page load if the
 * user is still unverified).
 *
 * The verification reminder fires when:
 *   - users.email_verified_at IS NULL, AND
 *   - (users.verify_reminder_at IS NULL
 *      OR users.verify_reminder_at < now() - INTERVAL 2 DAY)
 *
 * Two-day cadence is short enough to still be useful (we want them
 * to verify before they forget about it) and long enough not to feel
 * like spam every login.
 *
 * No backfill needed: every existing row gets NULL, so already-verified
 * users will never see the modal (gated on email_verified_at first),
 * and unverified users will see it on their next page load.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('verify_reminder_at')->nullable()->after('welcomed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('verify_reminder_at');
        });
    }
};
