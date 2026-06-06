<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the missing columns the TOTP 2FA flow needs on top of the
 * existing `two_factor_secret` + `two_factor_confirmed_at` pair:
 *
 *   two_factor_pending_secret
 *     - Holds the secret while the user is still mid-enrolment (after
 *       scanning the QR but before confirming a code). Separate from
 *       `two_factor_secret` so a partial enrolment doesn't accidentally
 *       lock anybody out — they only become "2FA-enabled" after the
 *       confirm step swaps pending → live.
 *
 *   two_factor_recovery_codes
 *     - JSON array of bcrypt-hashed one-time recovery codes. Stored as
 *       a TEXT column rather than an encrypted blob so each individual
 *       code can be matched + invalidated without round-tripping the
 *       full set through the framework's encryption.
 *
 *   require_2fa  (on `tenants`, central)
 *     - Tenant-level toggle that an admin can flip to force every user
 *       on the tenant to enrol. The login flow checks this after
 *       password auth and bounces the user into setup if they haven't
 *       enrolled yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'two_factor_pending_secret')) {
                $table->text('two_factor_pending_secret')->nullable()->after('two_factor_secret');
            }
            if (! Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_pending_secret');
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'require_2fa')) {
                $table->boolean('require_2fa')->default(false)->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->dropColumn('two_factor_recovery_codes');
            }
            if (Schema::hasColumn('users', 'two_factor_pending_secret')) {
                $table->dropColumn('two_factor_pending_secret');
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'require_2fa')) {
                $table->dropColumn('require_2fa');
            }
        });
    }
};
