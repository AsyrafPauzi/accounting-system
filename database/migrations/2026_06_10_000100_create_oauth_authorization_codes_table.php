<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-use authorization codes for the OAuth "Connect" handshake.
 *
 * Lifecycle (~10 minute window):
 *
 *   1. User clicks "Authorize" on /oauth/consent. We insert one row:
 *        code         = random 64-char URL-safe string
 *        tenant_id    = the tenant whose data they're authorising
 *        user_id      = the user who clicked Authorize (for audit)
 *        oauth_client_id = e.g. 'finpersona'
 *        redirect_uri = the URI we'll redirect back to (verified at
 *                       exchange to defeat code interception)
 *        expires_at   = now()+10 min
 *
 *   2. Browser redirects to the partner with `?code=<code>&state=<...>`.
 *
 *   3. Partner's backend POSTs /api/oauth/token with code + secret.
 *      We look up the row, verify the redirect_uri matches what the
 *      partner sent and that the row is unused + unexpired, mint the
 *      tenant_api_credentials row, mark `used_at`, and return the keys.
 *
 *   4. Even if the same code is replayed, used_at is non-null and the
 *      exchange refuses — defence against code interception or
 *      malicious mid-handshake interception.
 *
 * Why a real DB row and not a JWT-style stateless code? We need
 * idempotent single-use semantics that survive process restarts and
 * multi-instance deploys. A row with a `used_at` timestamp is the
 * simplest implementation; the table cleans itself up via the bin-
 * cleanup command (or just leave expired rows — they're tiny).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('oauth_authorization_codes', function (Blueprint $table) {
            $table->id();

            // The opaque code string we hand to the partner via
            // redirect. Unique because we generate it as 64 chars of
            // random bytes (csprng); an actual collision would mean
            // the OS RNG is compromised and we have bigger problems.
            $table->char('code', 64)->unique();

            $table->string('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnDelete();

            // User who completed consent. We carry this onto the
            // tenant_api_credentials row at exchange time so support
            // can answer "who authorised this integration?".
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('oauth_client_id', 64);
            $table->string('redirect_uri', 2048);

            // 10-minute window. After this the row is dead even if
            // unused. Index because the cleanup query filters on it.
            $table->timestamp('expires_at');
            $table->index('expires_at');

            // Single-use marker. NULL = available for exchange; non-null
            // = already redeemed (subsequent attempts return 400).
            $table->timestamp('used_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_authorization_codes');
    }
};
