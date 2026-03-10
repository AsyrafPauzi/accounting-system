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
        if (Schema::hasColumn('customers', 'billing_address')) $table->dropColumn('billing_address');
        if (Schema::hasColumn('customers', 'shipping_address')) $table->dropColumn('shipping_address');

        // New Detailed Columns
        $table->string('industry')->nullable()->after('name');
        $table->string('website')->nullable()->after('industry');
        $table->string('contact_person')->nullable()->after('website');
        
        // Granular Billing Address
        $table->text('billing_street')->nullable();
        $table->string('billing_city')->nullable();
        $table->string('billing_state')->nullable();
        $table->string('billing_zip')->nullable();
        $table->string('billing_country')->default('Malaysia');

        // Granular Shipping Address
        $table->text('shipping_street')->nullable();
        $table->string('shipping_city')->nullable();
        $table->string('shipping_state')->nullable();
        $table->string('shipping_zip')->nullable();
        $table->string('shipping_country')->default('Malaysia');

        // Internal Data
        $table->text('internal_notes')->nullable();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enterprise_v2', function (Blueprint $table) {
            //
        });
    }
};
