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
            if (!Schema::hasColumn('invoices', 'due_date')) {
                $table->date('due_date')->nullable()->after('issue_date');
            }
            if (!Schema::hasColumn('invoices', 'currency')) {
                $table->string('currency')->default('MYR')->after('invoice_number');
            }
            if (!Schema::hasColumn('invoices', 'exchange_rate')) {
                $table->decimal('exchange_rate', 15, 6)->default(1.000000);
            }
            if (!Schema::hasColumn('invoices', 'shipping_amount')) {
                $table->decimal('shipping_amount', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('invoices', 'rounding_adjustment')) {
                $table->decimal('rounding_adjustment', 5, 2)->default(0); // Malaysia 5-sen rounding
            }
            if (!Schema::hasColumn('invoices', 'private_notes')) {
                $table->text('private_notes')->nullable(); // Only seen by staff
            }
            if (!Schema::hasColumn('invoices', 'customer_notes')) {
                $table->text('customer_notes')->nullable(); // Seen on PDF
            }
            if (!Schema::hasColumn('invoices', 'created_by')) {
                // In tenant databases, we only need the ID, not the foreign key constraint,
                // because the users table lives in the central database.
                $table->unsignedBigInteger('created_by')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Intentionally left blank.
        });
    }
};

