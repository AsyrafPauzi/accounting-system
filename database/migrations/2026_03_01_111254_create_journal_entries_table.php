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
    Schema::create('journal_entries', function (Blueprint $table) {
        $table->id();
        $table->date('date');
        $table->string('description');
        $table->string('reference_type'); // e.g., 'Invoice'
        $table->unsignedBigInteger('reference_id');
        $table->timestamps();
    });

    Schema::create('journal_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('journal_entry_id')->constrained()->onDelete('cascade');
        $table->string('account_code'); // Links to our Chart of Accounts
        $table->decimal('debit', 15, 2)->default(0);
        $table->decimal('credit', 15, 2)->default(0);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
