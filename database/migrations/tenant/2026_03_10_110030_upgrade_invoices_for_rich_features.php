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
            if (Schema::hasColumn('invoices', 'status')) {
                $table->string('status')->default('draft')->change();
            }
            if (!Schema::hasColumn('invoices', 'discount_total')) {
                $table->decimal('discount_total', 15, 2)->default(0)->after('amount_before_tax');
            }
            if (!Schema::hasColumn('invoices', 'msic_code')) {
                $table->string('msic_code')->nullable()->after('invoice_number'); // Malaysia Compliance
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_items', 'discount_amount')) {
                $table->decimal('discount_amount', 15, 2)->default(0)->after('amount');
            }
            if (!Schema::hasColumn('invoice_items', 'item_classification')) {
                $table->string('item_classification')->nullable(); // LHDN Requirement (e.g., 022 for services)
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left blank.
    }
};

