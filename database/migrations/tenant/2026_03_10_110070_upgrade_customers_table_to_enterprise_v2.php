<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Drop old columns if they exist (clean up for enterprise)
            if (Schema::hasColumn('customers', 'billing_address')) {
                $table->dropColumn('billing_address');
            }
            if (Schema::hasColumn('customers', 'shipping_address')) {
                $table->dropColumn('shipping_address');
            }

            // New Detailed Columns
            if (!Schema::hasColumn('customers', 'industry')) {
                $table->string('industry')->nullable()->after('name');
            }
            if (!Schema::hasColumn('customers', 'website')) {
                $table->string('website')->nullable()->after('industry');
            }
            if (!Schema::hasColumn('customers', 'contact_person')) {
                $table->string('contact_person')->nullable()->after('website');
            }
            // Granular Billing Address
            if (!Schema::hasColumn('customers', 'billing_street')) {
                $table->text('billing_street')->nullable();
            }
            if (!Schema::hasColumn('customers', 'billing_city')) {
                $table->string('billing_city')->nullable();
            }
            if (!Schema::hasColumn('customers', 'billing_state')) {
                $table->string('billing_state')->nullable();
            }
            if (!Schema::hasColumn('customers', 'billing_zip')) {
                $table->string('billing_zip')->nullable();
            }
            if (!Schema::hasColumn('customers', 'billing_country')) {
                $table->string('billing_country')->default('Malaysia');
            }
            // Granular Shipping Address
            if (!Schema::hasColumn('customers', 'shipping_street')) {
                $table->text('shipping_street')->nullable();
            }
            if (!Schema::hasColumn('customers', 'shipping_city')) {
                $table->string('shipping_city')->nullable();
            }
            if (!Schema::hasColumn('customers', 'shipping_state')) {
                $table->string('shipping_state')->nullable();
            }
            if (!Schema::hasColumn('customers', 'shipping_zip')) {
                $table->string('shipping_zip')->nullable();
            }
            if (!Schema::hasColumn('customers', 'shipping_country')) {
                $table->string('shipping_country')->default('Malaysia');
            }
            if (!Schema::hasColumn('customers', 'internal_notes')) {
                $table->text('internal_notes')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Intentionally left blank.
        });
    }
};

