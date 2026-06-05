<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estimates (a.k.a. quotations). Pre-invoice documents the tenant sends to a
 * customer for approval. They never post to the General Ledger; only when an
 * estimate is "Converted" does the system create a real Invoice and journal
 * entry from it.
 *
 * Mirrors the invoice schema closely so converting from one to the other is
 * a simple field-for-field copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('estimates')) {
            Schema::create('estimates', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('estimate_number', 50)->unique();
                $table->string('currency', 3)->default('MYR');
                $table->decimal('exchange_rate', 14, 6)->default(1.0);
                $table->foreignId('customer_id')->constrained('customers');

                $table->date('issue_date');
                $table->date('expiry_date')->nullable()
                    ->comment('Date the quote is valid until. After this date the system marks it expired.');

                /*
                 * Lifecycle:
                 *   draft     — being edited; not yet shared
                 *   sent      — shared with customer (manually or via email)
                 *   accepted  — customer agreed; no invoice yet
                 *   rejected  — customer declined
                 *   expired   — past expiry_date with no decision
                 *   converted — an invoice has been generated from it
                 */
                $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'])->default('draft');

                $table->decimal('amount_before_tax', 15, 2)->default(0);
                $table->decimal('discount_total', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('shipping_amount', 15, 2)->default(0);
                $table->decimal('rounding_adjustment', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);

                $table->text('customer_notes')->nullable();
                $table->text('private_notes')->nullable()
                    ->comment('Internal-only — never rendered on the customer-facing PDF.');

                $table->unsignedBigInteger('converted_invoice_id')->nullable()
                    ->comment('Set when status=converted; soft FK to invoices.id (in same tenant DB).');
                $table->unsignedBigInteger('created_by')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'issue_date'], 'estimates_status_date_idx');
                $table->index('customer_id');
                $table->index('expiry_date');
            });
        }

        if (! Schema::hasTable('estimate_items')) {
            Schema::create('estimate_items', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('estimate_id')->constrained('estimates')->cascadeOnDelete();
                $table->unsignedBigInteger('product_id')->nullable()
                    ->comment('Optional soft FK to products.id; preserves which catalogue item populated this line.');

                $table->string('item_classification', 20)->nullable()
                    ->comment('Mirrors LHDN classification on invoice items.');
                $table->string('description', 500);
                $table->decimal('quantity', 10, 2)->default(1);
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->decimal('amount', 15, 2)->default(0)
                    ->comment('Computed: (quantity * unit_price) - discount_amount. Persisted for fast reads / reporting.');
                $table->unsignedInteger('display_order')->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->index('estimate_id');
                $table->index('product_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('estimate_items');
        Schema::dropIfExists('estimates');
    }
};
