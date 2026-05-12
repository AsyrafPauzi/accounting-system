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
        Schema::table('bills', function (Blueprint $table) {
            $table->string('receipt_path')->nullable()->after('reference');
            $table->string('ocr_status', 20)->default('none')->after('receipt_path');
            $table->json('ocr_data')->nullable()->after('ocr_status');
            $table->string('audit_status', 20)->default('unaudited')->after('ocr_data');
            $table->timestamp('audited_at')->nullable()->after('audit_status');
            $table->unsignedBigInteger('audited_by')->nullable()->after('audited_at');

            // Indexing for performance in Audit Module
            $table->index(['audit_status', 'bill_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn([
                'receipt_path',
                'ocr_status',
                'ocr_data',
                'audit_status',
                'audited_at',
                'audited_by'
            ]);
        });
    }
};
