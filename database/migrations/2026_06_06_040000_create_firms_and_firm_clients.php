<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema for the Accountant track — practices that manage multiple
 * client tenants from one place.
 *
 * Three tables, all on the central DB:
 *
 *   firms
 *     The practice itself. One row per accounting firm. Owns its own
 *     subscription (`firm_subscription_id` → `subscriptions.id`) so the
 *     existing billing pipeline (Toyyibpay + retention + downgrade
 *     scheduling) works unchanged — a "firm" is just another billing
 *     subject that happens not to have a tenant DB attached to it.
 *
 *   firm_clients
 *     Pivot from firm → tenant (client). The same tenant can appear in
 *     at most one firm at a time (unique key on tenant_id). Permission
 *     level lets us grow into "advisor / read-only" later without a
 *     migration; default is `admin` because that's how Malaysian SME
 *     firms work today (the firm is the bookkeeper).
 *
 *   firm_invitations
 *     Holds pending invites in either direction:
 *       - firm-initiated: firm wants to onboard a client → invite their
 *         email; they create a tenant and accept on first login.
 *       - tenant-initiated: existing tenant wants their accountant to
 *         take over → tenant_id is filled, firm_id is null until the
 *         firm accepts.
 *     A signed token + expiry keeps it phish-resistant.
 *
 * Index strategy is conservative: every column anyone is realistically
 * going to filter or join on gets an index. Firm-scale traffic will
 * never come close to needing partitioning, but the indexes make the
 * Practice console snappy from day one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();

            // Central-DB user who owns the firm — the person who signed
            // up. Their `firm_id` column is set to this firm's id.
            $table->foreignId('owner_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Pointer to the firm-level subscription row in
            // `subscriptions`. Nullable until checkout completes.
            $table->foreignId('firm_subscription_id')
                ->nullable()
                ->constrained('subscriptions')
                ->nullOnDelete();

            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('country', 2)->default('MY');

            // Status mirrors `subscriptions.status` so we can disable
            // the practice console wholesale without nullifying the
            // subscription pointer (e.g. payment lapsed).
            $table->string('status', 32)->default('active');

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('owner_user_id');
        });

        Schema::create('firm_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained('firms')->cascadeOnDelete();
            // Tenant id is a string in this app (slug-based), not a
            // bigint, so we declare it manually.
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            // Permission level the firm has on this client. `admin` =
            // full access; `editor` = no admin settings; `viewer` =
            // read-only. Default `admin` matches the typical MY SME
            // bookkeeper relationship.
            $table->string('permission_level', 16)->default('admin');

            // Lifecycle — `active` once accepted, `pending` while
            // awaiting the other side's confirmation.
            $table->string('status', 16)->default('active');

            $table->timestamp('linked_at')->nullable();
            $table->foreignId('linked_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique('tenant_id', 'firm_clients_tenant_unique');
            $table->index(['firm_id', 'status']);
        });

        Schema::create('firm_invitations', function (Blueprint $table) {
            $table->id();
            // One side of the pair is always known; the other is filled
            // when the recipient accepts.
            $table->foreignId('firm_id')->nullable()->constrained('firms')->cascadeOnDelete();
            $table->string('tenant_id')->nullable();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('direction', 16); // 'firm_invites_client' | 'client_invites_firm'
            $table->string('email');         // recipient
            $table->string('token', 64)->unique();

            // What permission level we'll grant on accept. Stored at
            // invitation time so the inviter's intent is captured even
            // if firm-side defaults change before acceptance.
            $table->string('permission_level', 16)->default('admin');

            $table->string('status', 16)->default('pending'); // pending | accepted | revoked | expired
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['email', 'status']);
            $table->index(['firm_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });

        // Link table on `users` so a firm-staff user can be retrieved
        // without joining through firm_clients on every request. Null
        // for normal SME tenant users.
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'firm_id')) {
                $table->foreignId('firm_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained('firms')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'firm_role')) {
                // 'owner' | 'staff' | null for non-firm users
                $table->string('firm_role', 16)->nullable()->after('firm_id');
            }
        });

        // Mirror flag on `subscriptions` so we can tell a firm-billing
        // subscription from a tenant-billing one without joining.
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'firm_id')) {
                $table->foreignId('firm_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained('firms')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'firm_id')) {
                $table->dropForeign(['firm_id']);
                $table->dropColumn('firm_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'firm_role')) {
                $table->dropColumn('firm_role');
            }
            if (Schema::hasColumn('users', 'firm_id')) {
                $table->dropForeign(['firm_id']);
                $table->dropColumn('firm_id');
            }
        });

        Schema::dropIfExists('firm_invitations');
        Schema::dropIfExists('firm_clients');
        Schema::dropIfExists('firms');
    }
};
