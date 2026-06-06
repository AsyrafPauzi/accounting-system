<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `max_tenants` to `self_hosted_installs`.
 *
 * Why a new column instead of stuffing it into `features`? Because
 * the value is numeric and the admin UI needs to render a nullable
 * integer cap. Keeping it as a top-level claim also makes a future
 * "renew with expanded cap" workflow easier to express in SQL.
 *
 * Standard tier defaults to 1, Enterprise tier defaults to 0
 * (unlimited). Existing rows backfilled to 0 (the safest default
 * for already-issued licenses — those were issued before we cared
 * about a tenant cap).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('self_hosted_installs', function (Blueprint $table) {
            $table->unsignedInteger('max_tenants')->default(0)->after('max_users');
        });
    }

    public function down(): void
    {
        Schema::table('self_hosted_installs', function (Blueprint $table) {
            $table->dropColumn('max_tenants');
        });
    }
};
