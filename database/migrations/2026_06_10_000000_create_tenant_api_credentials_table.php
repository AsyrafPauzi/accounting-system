<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant API credentials. One row per tenant client integration.
 *
 * The flow that produces a row in this table:
 *
 *   1. Tenant admin generates a key from Settings → Integrations.
 *   2. User logs in on a forked branded login page, hits the consent
 *      screen, clicks "Authorize"
 *   3. We mint an `api_key` (public-ish identifier the partner sends in
 *      Authorization: Bearer ...) and a `transaction_signing_key`
 *      (HMAC secret the partner uses to sign mutating request bodies).
 *      Both values are revealed once, after which only the masked
 *      preview and revocation are exposed.
 *
 * Storage model:
 *
 *   - `api_key_hash` is a SHA-256 hash of the plaintext key. We do NOT
 *     store the plaintext: the partner keeps it in their config; we
 *     just look it up by hash on every authenticated request. This
 *     means a DB leak does not leak working API keys.
 *   - `api_key_last4` lets the user identify which row is which on the
 *     /settings/integrations page without us decrypting anything.
 *   - `transaction_signing_key` is encrypted at rest with `Crypt::
 *     encryptString` (same pattern as `OcrSettings::gemini_api_key`)
 *     because we DO need to recover the plaintext server-side to verify
 *     incoming HMAC signatures. Partners who lose the key must rotate.
 *
 * Plan gate: this table only ever has rows for tenants whose plan
 * grants `api.access` (Solo and up). The issuance controller refuses
 * to write a row for a Startup tenant.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_api_credentials', function (Blueprint $table) {
            $table->id();

            // Tenant whose data the API key authorises access to.
            // Stored as string FK to mirror the rest of the central-DB
            // tenant relations (subscriptions, etc.). Cascade delete
            // because revoking the tenant should invalidate every
            // credential in one go.
            $table->string('tenant_id');
            $table->index('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnDelete();

            // Client slug (e.g. 'direct').
            $table->string('oauth_client_id', 64);
            $table->index('oauth_client_id');

            // SHA-256 hash of the plaintext API key. Indexed for
            // O(log n) lookup during request authentication. Unique
            // because two rows hashing to the same value would mean
            // the random key generator collided — defence in depth.
            $table->char('api_key_hash', 64)->unique();

            // Last four characters of the plaintext key, kept solely
            // for masked UI display ("•••• 8j2k"). NEVER used for auth.
            $table->string('api_key_last4', 4);

            // Encrypted-at-rest HMAC signing secret. `text` because
            // Crypt::encryptString output is much longer than the
            // plaintext input (IV + payload + MAC envelope).
            $table->text('transaction_signing_key');

            // Same trick as api_key_last4 so the UI can render
            // "•••• 4d7f" without us decrypting on every page render.
            $table->string('signing_key_last4', 4);

            // Audit trail — the user who clicked "Authorize" on the
            // consent screen. NULL only if the issuing user got hard-
            // deleted later (we keep the row so revocation history
            // survives a user purge).
            $table->foreignId('issued_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Updated on every successful authenticated API request.
            // Lets the UI show "Last used: 3 minutes ago" and gives
            // operators a signal for stale-credential cleanup.
            $table->timestamp('last_used_at')->nullable();

            // Manual revocation. NULL = active; non-null = revoked at
            // this moment. We keep the row instead of hard-deleting so
            // the audit trail ("revoked by Asyraf on 12 Jun") survives.
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_api_credentials');
    }
};
