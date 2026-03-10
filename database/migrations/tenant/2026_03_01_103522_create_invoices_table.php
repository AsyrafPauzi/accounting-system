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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number');
            $table->foreignId('customer_id');
            $table->date('issue_date');
            $table->decimal('amount_before_tax', 15, 2);
            $table->decimal('tax_amount', 15, 2); // SST 6% or 8%
            $table->decimal('total_amount', 15, 2);
            
            // LHDN Fields (Malaysia)
            $table->string('lhdn_status')->default('pending'); 
            $table->string('lhdn_uuid')->nullable(); // For E-invoice unique ID
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
