<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beyond Sales parity: CN cash refunds, late-fee invoices, recurring auto-post.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credit_notes') && ! Schema::hasColumn('credit_notes', 'refunded_amount')) {
            Schema::table('credit_notes', function (Blueprint $table) {
                $table->decimal('refunded_amount', 15, 2)->default(0)->after('applied_amount');
            });
        }

        if (! Schema::hasTable('credit_note_refunds')) {
            Schema::create('credit_note_refunds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('credit_note_id')->constrained('credit_notes')->cascadeOnDelete();
                $table->decimal('amount', 15, 2);
                $table->date('payment_date');
                $table->string('bank_account_code', 20);
                $table->string('reference')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('invoices', 'is_late_fee')) {
                    $table->boolean('is_late_fee')->default(false);
                }
            });
        }

        if (Schema::hasTable('recurring_invoices') && ! Schema::hasColumn('recurring_invoices', 'auto_post')) {
            Schema::table('recurring_invoices', function (Blueprint $table) {
                $table->boolean('auto_post')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_refunds');
        if (Schema::hasTable('credit_notes') && Schema::hasColumn('credit_notes', 'refunded_amount')) {
            Schema::table('credit_notes', function (Blueprint $table) {
                $table->dropColumn('refunded_amount');
            });
        }
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'is_late_fee')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('is_late_fee');
            });
        }
        if (Schema::hasTable('recurring_invoices') && Schema::hasColumn('recurring_invoices', 'auto_post')) {
            Schema::table('recurring_invoices', function (Blueprint $table) {
                $table->dropColumn('auto_post');
            });
        }
    }
};
