<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the "contact sales" flag for tiers we don't price online.
 *
 * Why a flag instead of a sentinel price like 0 or NULL?
 *   The Subscription page already renders RM 0 plans as "Free" (the Startup
 *   tier), and we want a different UX for Enterprise: no price shown, a
 *   "Talk to sales" CTA, and the checkout endpoint must refuse to create a
 *   bill for these. A boolean is the clearest way to express that intent.
 *
 * Most Enterprise customers we expect to land want self-hosted deployments
 * with a custom contract anyway, so an automated checkout would never have
 * been the right flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'is_contact_sales')) {
                $table->boolean('is_contact_sales')->default(false)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (Schema::hasColumn('plans', 'is_contact_sales')) {
                $table->dropColumn('is_contact_sales');
            }
        });
    }
};
