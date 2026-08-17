<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment reverse, deposit leftover close, Pay Now provider refs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_payments')) {
            Schema::table('invoice_payments', function (Blueprint $table) {
                if (! Schema::hasColumn('invoice_payments', 'reversed_at')) {
                    $table->timestamp('reversed_at')->nullable();
                }
                if (! Schema::hasColumn('invoice_payments', 'reversed_by')) {
                    $table->unsignedBigInteger('reversed_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('ar_deposits')) {
            Schema::table('ar_deposits', function (Blueprint $table) {
                if (! Schema::hasColumn('ar_deposits', 'refunded_amount')) {
                    $table->decimal('refunded_amount', 15, 2)->default(0);
                }
                if (! Schema::hasColumn('ar_deposits', 'forfeited_amount')) {
                    $table->decimal('forfeited_amount', 15, 2)->default(0);
                }
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('invoices', 'pay_now_provider')) {
                    $table->string('pay_now_provider', 32)->nullable();
                }
                if (! Schema::hasColumn('invoices', 'pay_now_reference')) {
                    $table->string('pay_now_reference', 120)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Keep columns — tenant books may already have reversed payments.
    }
};
