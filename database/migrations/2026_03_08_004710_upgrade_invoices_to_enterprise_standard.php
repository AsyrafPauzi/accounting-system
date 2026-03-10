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
        $table->date('due_date')->nullable()->after('issue_date');
        $table->string('currency')->default('MYR')->after('invoice_number');
        $table->decimal('exchange_rate', 15, 6)->default(1.000000);
        
        // Advanced Totals
        $table->decimal('shipping_amount', 15, 2)->default(0);
        $table->decimal('rounding_adjustment', 5, 2)->default(0); // Malaysia 5-sen rounding
        
        // Enterprise Tracking
        $table->text('private_notes')->nullable(); // Only seen by staff
        $table->text('customer_notes')->nullable(); // Seen on PDF
        $table->foreignId('created_by')->nullable()->constrained('users');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enterprise_standard', function (Blueprint $table) {
            //
        });
    }
};
