<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records that a user accepted the privacy policy at registration.
 *
 * Two columns instead of just one boolean so we can answer two real PDPA
 * questions later: "did this user consent?" and "to which version of the
 * policy?". The version column means we can re-prompt only when material
 * changes ship — minor edits don't force everybody back through a modal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'privacy_accepted_at')) {
                $table->timestamp('privacy_accepted_at')->nullable()->after('remember_token');
            }
            if (! Schema::hasColumn('users', 'privacy_accepted_version')) {
                $table->string('privacy_accepted_version', 16)->nullable()->after('privacy_accepted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'privacy_accepted_version')) {
                $table->dropColumn('privacy_accepted_version');
            }
            if (Schema::hasColumn('users', 'privacy_accepted_at')) {
                $table->dropColumn('privacy_accepted_at');
            }
        });
    }
};
