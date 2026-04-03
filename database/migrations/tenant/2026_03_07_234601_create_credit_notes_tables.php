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
    // 1. Credit Notes Header
    Schema::create('credit_notes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
        $table->foreignId('customer_id')->constrained();
        $table->string('cn_number')->unique();
        $table->date('issue_date');
        $table->string('reason_code'); // LHDN Requirement (01, 02, 03, 04)
        $table->text('reason_description')->nullable();
        $table->decimal('total_amount', 15, 2);
        $table->string('status')->default('draft'); // draft, posted, void
        $table->timestamps();
    });

    // 2. Credit Note Items
    Schema::create('credit_note_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('credit_note_id')->constrained()->onDelete('cascade');
        $table->string('description');
        $table->decimal('quantity', 10, 2);
        $table->decimal('unit_price', 15, 2);
        $table->decimal('tax_rate', 5, 2);
        $table->decimal('amount', 15, 2);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_notes_tables');
    }
};
