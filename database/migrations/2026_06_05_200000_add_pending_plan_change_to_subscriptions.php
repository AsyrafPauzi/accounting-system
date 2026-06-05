<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the "scheduled change" fields used by downgrade flow.
 *
 *   pending_plan_id   — the plan the tenant has chosen to switch to once
 *                       the current period ends.
 *   pending_interval  — billing cadence the new plan should run on
 *                       (mirrors the existing `interval` column).
 *
 * Picked over an audit table because there's only ever one pending change
 * per subscription — picking a new plan replaces any earlier scheduled
 * change, and applying it clears the columns. A separate table would imply
 * history we don't need.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'pending_plan_id')) {
                $table->foreignId('pending_plan_id')->nullable()->after('plan_id')->constrained('plans')->nullOnDelete();
            }
            if (! Schema::hasColumn('subscriptions', 'pending_interval')) {
                $table->string('pending_interval', 16)->nullable()->after('pending_plan_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'pending_interval')) {
                $table->dropColumn('pending_interval');
            }
            if (Schema::hasColumn('subscriptions', 'pending_plan_id')) {
                $table->dropConstrainedForeignId('pending_plan_id');
            }
        });
    }
};
