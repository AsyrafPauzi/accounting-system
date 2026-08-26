<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_payments') && ! Schema::hasColumn('invoice_payments', 'receipt_number')) {
            Schema::table('invoice_payments', function (Blueprint $table) {
                $table->string('receipt_number', 40)->nullable()->unique()->after('reference');
            });
        }

        if (Schema::hasTable('bill_payments') && ! Schema::hasColumn('bill_payments', 'voucher_number')) {
            Schema::table('bill_payments', function (Blueprint $table) {
                $table->string('voucher_number', 40)->nullable()->unique()->after('reference');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoice_payments') && Schema::hasColumn('invoice_payments', 'receipt_number')) {
            Schema::table('invoice_payments', function (Blueprint $table) {
                $table->dropUnique(['receipt_number']);
                $table->dropColumn('receipt_number');
            });
        }

        if (Schema::hasTable('bill_payments') && Schema::hasColumn('bill_payments', 'voucher_number')) {
            Schema::table('bill_payments', function (Blueprint $table) {
                $table->dropUnique(['voucher_number']);
                $table->dropColumn('voucher_number');
            });
        }
    }
};
