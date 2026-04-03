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
    Schema::table('invoices', function (Blueprint $table) {
        // Status should allow: draft, sent, paid, void
        $table->string('status')->default('draft')->change();
        $table->decimal('discount_total', 15, 2)->default(0)->after('amount_before_tax');
        $table->string('msic_code')->nullable()->after('invoice_number'); // Malaysia Compliance
    });

    Schema::table('invoice_items', function (Blueprint $table) {
        $table->decimal('discount_amount', 15, 2)->default(0)->after('amount');
        $table->string('item_classification')->nullable(); // LHDN Requirement (e.g., 022 for services)
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
