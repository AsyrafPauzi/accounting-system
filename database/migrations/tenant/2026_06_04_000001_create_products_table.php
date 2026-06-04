<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue of reusable invoice line items: a product, service, or recurring
 * fee that the tenant sells. Used to pre-fill description / price / account /
 * tax on invoice lines, and later on estimate and recurring-invoice lines.
 *
 * Soft-deleted so historic invoices that referenced a product keep an
 * audit trail even if the tenant later removes the product from the catalogue.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            return;
        }

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 50)->nullable()->unique()->comment('Optional SKU-style identifier shown alongside name.');
            $table->string('name', 150);
            $table->text('description')->nullable()->comment('Long-form text used as the default invoice line description.');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->string('account_code', 20)->nullable()->comment('Default revenue account (e.g. 4000); soft FK to accounts.code.');
            $table->decimal('tax_rate', 5, 2)->default(0)->comment('Default tax % e.g. 6.00 for 6% SST.');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'display_order'], 'products_active_order_idx');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
