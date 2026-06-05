<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring invoices: a template + schedule that the system uses to auto-create
 * draft invoices on a cadence (weekly / monthly / quarterly / yearly).
 *
 * Decision (2026-06-04): every cycle creates a DRAFT invoice. The user reviews
 * and posts manually. No auto-email, no auto-post.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recurring_invoices')) {
            Schema::create('recurring_invoices', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name', 150)->nullable()
                    ->comment('Internal label e.g. "Acme Corp — monthly retainer". Not shown to customer.');

                $table->foreignId('customer_id')->constrained('customers');

                /*
                 * Cadence + interval together describe the schedule:
                 *   cadence=monthly, interval=1  → every month
                 *   cadence=monthly, interval=3  → every 3 months
                 *   cadence=weekly,  interval=2  → every 2 weeks
                 */
                $table->enum('cadence', ['weekly', 'monthly', 'quarterly', 'yearly'])->default('monthly');
                $table->unsignedSmallInteger('interval')->default(1);

                $table->date('start_date');
                $table->date('end_date')->nullable()
                    ->comment('Open-ended when null. The schedule stops generating after this date.');

                $table->date('next_run_date')
                    ->comment('Next date the daily cron will materialise an invoice from this template.');
                $table->date('last_run_date')->nullable();

                $table->unsignedBigInteger('last_generated_invoice_id')->nullable();
                $table->unsignedInteger('generated_count')->default(0);

                $table->boolean('is_active')->default(true);

                $table->string('currency', 3)->default('MYR');
                $table->decimal('exchange_rate', 14, 6)->default(1.0);
                $table->decimal('shipping_amount', 15, 2)->default(0);
                $table->unsignedSmallInteger('payment_terms_days')->default(30)
                    ->comment('When generating, due_date = issue_date + this many days.');
                $table->string('msic_code', 10)->default('00000');

                $table->text('customer_notes')->nullable();
                $table->text('private_notes')->nullable();

                $table->unsignedBigInteger('created_by')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['is_active', 'next_run_date'], 'recurring_invoices_due_idx');
                $table->index('customer_id');
            });
        }

        if (! Schema::hasTable('recurring_invoice_items')) {
            Schema::create('recurring_invoice_items', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('recurring_invoice_id')->constrained('recurring_invoices')->cascadeOnDelete();
                $table->unsignedBigInteger('product_id')->nullable()
                    ->comment('Optional soft FK to products.id; preserves which catalogue item populated this line.');

                $table->string('item_classification', 20)->nullable();
                $table->string('description', 500);
                $table->decimal('quantity', 10, 2)->default(1);
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->unsignedInteger('display_order')->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->index('recurring_invoice_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_invoice_items');
        Schema::dropIfExists('recurring_invoices');
    }
};
