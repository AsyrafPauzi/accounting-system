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
        // Identity
        $table->string('code')->unique()->after('id'); // e.g., CUST-1001
     
        // Financials
        $table->decimal('credit_limit', 15, 2)->default(0);
        $table->integer('payment_terms')->default(30); // days
        $table->string('currency')->default('MYR');
    
        
        // Status
        $table->boolean('is_active')->default(true);
   
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enterprise', function (Blueprint $table) {
            //
        });
    }
};
