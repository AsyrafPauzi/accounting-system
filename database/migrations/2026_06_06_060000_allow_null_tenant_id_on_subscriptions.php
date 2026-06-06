<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Practice (Accountant track) subscriptions belong to a `firm` rather
 * than a `tenant`, so we need `tenant_id` to be nullable. Tenant-side
 * subscriptions remain unchanged — they always carry a tenant_id, and
 * a check constraint at the application layer (Subscription model)
 * enforces "exactly one of tenant_id / firm_id is set".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Don't try to undo: existing firm-billing rows would fail the
        // not-null constraint. Leaving down() as a no-op is correct
        // for an additive schema relaxation.
    }
};
