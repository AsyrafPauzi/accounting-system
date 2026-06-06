<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `client_cap` to plans for Practice-tier sizing.
 *
 * NULL means unlimited (Practice Firm). For SME plans this column
 * stays NULL — it's only meaningful when audience='practice'.
 *
 * Stored as a column rather than computed in PHP so super-admins can
 * tweak caps from the admin UI without a code change, and so the cap
 * is visible to anyone reading the schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'client_cap')) {
                $table->unsignedInteger('client_cap')
                    ->nullable()
                    ->after('users_included')
                    ->comment('Max client tenants for Practice plans; null = unlimited');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (Schema::hasColumn('plans', 'client_cap')) {
                $table->dropColumn('client_cap');
            }
        });
    }
};
