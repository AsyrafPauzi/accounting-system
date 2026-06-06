<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Server-side (publisher / SaaS) tables for tracking customer
 * self-hosted installs.
 *
 * Two tables:
 *
 *   self_hosted_installs
 *     One row per license/install (keyed by `license_id`). Records
 *     the latest heartbeat metadata so support / sales can see
 *     "is this customer healthy, what version are they on, how
 *     many users do they have". `revoked_at` is what we set when
 *     a license is killed; the heartbeat endpoint reads this and
 *     tells the customer to enter "expired" mode.
 *
 *   self_hosted_heartbeats
 *     Append-only history of heartbeats for forensics + churn /
 *     trend analysis. Cap retained rows via `retention:purge` later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('self_hosted_installs', function (Blueprint $table) {
            $table->id();
            $table->string('license_id', 64)->unique();
            $table->string('customer_id');
            $table->string('customer_name');
            $table->string('plan_tier');
            $table->unsignedInteger('max_users')->default(0);
            $table->json('features')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('issued_at');
            $table->string('latest_version', 32)->nullable();
            $table->unsignedInteger('latest_user_count')->nullable();
            $table->json('latest_payload')->nullable();
            $table->ipAddress('latest_ip')->nullable();
            $table->timestamp('latest_heartbeat_at')->nullable();
            $table->timestamp('first_heartbeat_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('latest_heartbeat_at');
        });

        Schema::create('self_hosted_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('install_id')->constrained('self_hosted_installs')->cascadeOnDelete();
            $table->string('version', 32)->nullable();
            $table->unsignedInteger('user_count')->nullable();
            $table->json('payload')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->timestamp('received_at');

            $table->index(['install_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_hosted_heartbeats');
        Schema::dropIfExists('self_hosted_installs');
    }
};
