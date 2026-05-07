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
            $table->index('status');
            $table->index('issue_date');
            $table->index('due_date');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->index('status');
            $table->index('bill_date');
            $table->index('due_date');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index('is_active');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->index('is_active');
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->index('invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['issue_date']);
            $table->dropIndex(['due_date']);
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['bill_date']);
            $table->dropIndex(['due_date']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });
    }
};
