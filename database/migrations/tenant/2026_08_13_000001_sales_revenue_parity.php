<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sales (Revenue) parity: invoice line products, credit-note completeness,
 * debit notes, cash sale / reminders / MyInvois columns, SO/DO, knock-off
 * payments, attachments, AR deposits, consolidated e-invoices.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->alterInvoiceItems();
        $this->alterInvoices();
        $this->alterCreditNotes();
        $this->createCreditNoteApplications();
        $this->alterProducts();
        $this->createDebitNotes();
        $this->createSalesOrders();
        $this->createDeliveryOrders();
        $this->createInvoicePayments();
        $this->createInvoiceAttachments();
        $this->createArDeposits();
        $this->createConsolidatedEInvoices();
        $this->ensureCustomerDepositsAccount();
        $this->alterRecurringInvoices();
    }

    public function down(): void
    {
        Schema::dropIfExists('consolidated_e_invoice_items');
        Schema::dropIfExists('consolidated_e_invoices');
        Schema::dropIfExists('ar_deposits');
        Schema::dropIfExists('invoice_attachments');
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('delivery_order_items');
        Schema::dropIfExists('delivery_orders');
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('debit_note_items');
        Schema::dropIfExists('debit_notes');
        Schema::dropIfExists('credit_note_applications');
    }

    private function alterInvoiceItems(): void
    {
        if (! Schema::hasTable('invoice_items')) {
            return;
        }
        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->after('invoice_id');
                $table->index('product_id');
            }
            if (! Schema::hasColumn('invoice_items', 'account_code')) {
                $table->string('account_code', 20)->nullable()->after('product_id');
            }
        });
    }

    private function alterInvoices(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }
        Schema::table('invoices', function (Blueprint $table) {
            $cols = [
                'lhdn_long_id' => fn (Blueprint $t) => $t->string('lhdn_long_id', 80)->nullable(),
                'lhdn_submitted_at' => fn (Blueprint $t) => $t->timestamp('lhdn_submitted_at')->nullable(),
                'lhdn_cancelled_at' => fn (Blueprint $t) => $t->timestamp('lhdn_cancelled_at')->nullable(),
                'lhdn_reject_reason' => fn (Blueprint $t) => $t->text('lhdn_reject_reason')->nullable(),
                'lhdn_qr_url' => fn (Blueprint $t) => $t->string('lhdn_qr_url', 500)->nullable(),
                'last_viewed_at' => fn (Blueprint $t) => $t->timestamp('last_viewed_at')->nullable(),
                'view_count' => fn (Blueprint $t) => $t->unsignedInteger('view_count')->default(0),
                'last_reminded_at' => fn (Blueprint $t) => $t->timestamp('last_reminded_at')->nullable(),
                'reminder_stage' => fn (Blueprint $t) => $t->string('reminder_stage', 20)->nullable(),
                'reminder_overrides' => fn (Blueprint $t) => $t->json('reminder_overrides')->nullable(),
                'is_cash_sale' => fn (Blueprint $t) => $t->boolean('is_cash_sale')->default(false),
                'payment_terms_days' => fn (Blueprint $t) => $t->unsignedSmallInteger('payment_terms_days')->nullable(),
                'sales_order_id' => fn (Blueprint $t) => $t->unsignedBigInteger('sales_order_id')->nullable(),
                'delivery_order_id' => fn (Blueprint $t) => $t->unsignedBigInteger('delivery_order_id')->nullable(),
                'estimate_id' => fn (Blueprint $t) => $t->unsignedBigInteger('estimate_id')->nullable(),
                'source_invoice_id' => fn (Blueprint $t) => $t->unsignedBigInteger('source_invoice_id')->nullable(),
                'is_consolidated' => fn (Blueprint $t) => $t->boolean('is_consolidated')->default(false),
                'consolidated_e_invoice_id' => fn (Blueprint $t) => $t->unsignedBigInteger('consolidated_e_invoice_id')->nullable(),
                'toyyibpay_bill_code' => fn (Blueprint $t) => $t->string('toyyibpay_bill_code', 80)->nullable(),
            ];
            foreach ($cols as $name => $add) {
                if (! Schema::hasColumn('invoices', $name)) {
                    $add($table);
                }
            }
        });
    }

    private function alterCreditNotes(): void
    {
        if (! Schema::hasTable('credit_notes')) {
            return;
        }

        try {
            Schema::table('credit_notes', function (Blueprint $table) {
                $table->dropForeign(['invoice_id']);
            });
        } catch (\Throwable) {
            // Some tenant DBs never created the FK.
        }

        Schema::table('credit_notes', function (Blueprint $table) {
            if (Schema::hasColumn('credit_notes', 'invoice_id')) {
                $table->unsignedBigInteger('invoice_id')->nullable()->change();
            }
            if (! Schema::hasColumn('credit_notes', 'amount_before_tax')) {
                $table->decimal('amount_before_tax', 15, 2)->default(0)->after('reason_description');
            }
            if (! Schema::hasColumn('credit_notes', 'tax_amount')) {
                $table->decimal('tax_amount', 15, 2)->default(0);
            }
            if (! Schema::hasColumn('credit_notes', 'applied_amount')) {
                $table->decimal('applied_amount', 15, 2)->default(0);
            }
            if (! Schema::hasColumn('credit_notes', 'currency')) {
                $table->string('currency', 3)->default('MYR');
            }
            if (! Schema::hasColumn('credit_notes', 'exchange_rate')) {
                $table->decimal('exchange_rate', 18, 6)->default(1);
            }
            if (! Schema::hasColumn('credit_notes', 'customer_notes')) {
                $table->text('customer_notes')->nullable();
            }
            if (! Schema::hasColumn('credit_notes', 'lhdn_status')) {
                $table->string('lhdn_status')->default('pending');
            }
            if (! Schema::hasColumn('credit_notes', 'lhdn_uuid')) {
                $table->string('lhdn_uuid')->nullable();
            }
            if (! Schema::hasColumn('credit_notes', 'lhdn_long_id')) {
                $table->string('lhdn_long_id', 80)->nullable();
            }
            if (! Schema::hasColumn('credit_notes', 'lhdn_submitted_at')) {
                $table->timestamp('lhdn_submitted_at')->nullable();
            }
            if (! Schema::hasColumn('credit_notes', 'lhdn_cancelled_at')) {
                $table->timestamp('lhdn_cancelled_at')->nullable();
            }
            if (! Schema::hasColumn('credit_notes', 'lhdn_reject_reason')) {
                $table->text('lhdn_reject_reason')->nullable();
            }
            if (! Schema::hasColumn('credit_notes', 'lhdn_qr_url')) {
                $table->string('lhdn_qr_url', 500)->nullable();
            }
            if (! Schema::hasColumn('credit_notes', 'last_emailed_at')) {
                $table->timestamp('last_emailed_at')->nullable();
            }
            if (! Schema::hasColumn('credit_notes', 'last_emailed_to')) {
                $table->string('last_emailed_to')->nullable();
            }
            if (! Schema::hasColumn('credit_notes', 'last_emailed_status')) {
                $table->string('last_emailed_status', 20)->nullable();
            }
            if (! Schema::hasColumn('credit_notes', 'last_emailed_error')) {
                $table->text('last_emailed_error')->nullable();
            }
        });

        try {
            Schema::table('credit_notes', function (Blueprint $table) {
                $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
            });
        } catch (\Throwable) {
        }

        if (Schema::hasTable('credit_note_items')) {
            Schema::table('credit_note_items', function (Blueprint $table) {
                if (! Schema::hasColumn('credit_note_items', 'product_id')) {
                    $table->unsignedBigInteger('product_id')->nullable()->after('credit_note_id');
                }
                if (! Schema::hasColumn('credit_note_items', 'account_code')) {
                    $table->string('account_code', 20)->nullable();
                }
                if (! Schema::hasColumn('credit_note_items', 'discount_amount')) {
                    $table->decimal('discount_amount', 15, 2)->default(0);
                }
                if (! Schema::hasColumn('credit_note_items', 'item_classification')) {
                    $table->string('item_classification', 20)->nullable();
                }
            });
        }
    }

    private function createCreditNoteApplications(): void
    {
        if (Schema::hasTable('credit_note_applications')) {
            return;
        }
        Schema::create('credit_note_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained('credit_notes')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->index(['invoice_id', 'credit_note_id']);
        });
    }

    private function alterProducts(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'classification_code')) {
                $table->string('classification_code', 20)->nullable()->after('tax_rate');
            }
        });
    }

    private function createDebitNotes(): void
    {
        if (! Schema::hasTable('debit_notes')) {
            Schema::create('debit_notes', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('invoice_id')->nullable();
                $table->foreignId('customer_id')->constrained();
                $table->string('dn_number')->unique();
                $table->date('issue_date');
                $table->string('reason_code')->nullable();
                $table->text('reason_description')->nullable();
                $table->decimal('amount_before_tax', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->string('currency', 3)->default('MYR');
                $table->decimal('exchange_rate', 18, 6)->default(1);
                $table->text('customer_notes')->nullable();
                $table->string('status')->default('posted');
                $table->string('lhdn_status')->default('pending');
                $table->string('lhdn_uuid')->nullable();
                $table->string('lhdn_long_id', 80)->nullable();
                $table->timestamp('lhdn_submitted_at')->nullable();
                $table->timestamp('lhdn_cancelled_at')->nullable();
                $table->text('lhdn_reject_reason')->nullable();
                $table->string('lhdn_qr_url', 500)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (! Schema::hasTable('debit_note_items')) {
            Schema::create('debit_note_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('debit_note_id')->constrained('debit_notes')->cascadeOnDelete();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('account_code', 20)->nullable();
                $table->string('description');
                $table->decimal('quantity', 10, 2);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->string('item_classification', 20)->nullable();
                $table->decimal('amount', 15, 2);
                $table->timestamps();
            });
        }
    }

    private function createSalesOrders(): void
    {
        if (! Schema::hasTable('sales_orders')) {
            Schema::create('sales_orders', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('so_number');
                $table->foreignId('customer_id')->constrained();
                $table->unsignedBigInteger('estimate_id')->nullable();
                $table->date('issue_date');
                $table->date('expected_date')->nullable();
                $table->string('status')->default('draft');
                $table->string('currency', 3)->default('MYR');
                $table->decimal('exchange_rate', 18, 6)->default(1);
                $table->decimal('amount_before_tax', 15, 2)->default(0);
                $table->decimal('discount_total', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('shipping_amount', 15, 2)->default(0);
                $table->decimal('rounding_adjustment', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->text('customer_notes')->nullable();
                $table->text('private_notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['status', 'issue_date']);
            });
        }
        if (! Schema::hasTable('sales_order_items')) {
            Schema::create('sales_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('account_code', 20)->nullable();
                $table->string('item_classification', 20)->nullable();
                $table->string('description');
                $table->decimal('quantity', 12, 4);
                $table->decimal('qty_delivered', 12, 4)->default(0);
                $table->decimal('qty_invoiced', 12, 4)->default(0);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('amount', 15, 2);
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();
            });
        }
    }

    private function createDeliveryOrders(): void
    {
        if (! Schema::hasTable('delivery_orders')) {
            Schema::create('delivery_orders', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('do_number');
                $table->foreignId('customer_id')->constrained();
                $table->unsignedBigInteger('sales_order_id')->nullable();
                $table->date('issue_date');
                $table->date('delivery_date')->nullable();
                $table->string('status')->default('draft');
                $table->string('currency', 3)->default('MYR');
                $table->text('customer_notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (! Schema::hasTable('delivery_order_items')) {
            Schema::create('delivery_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('delivery_order_id')->constrained('delivery_orders')->cascadeOnDelete();
                $table->unsignedBigInteger('sales_order_item_id')->nullable();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('description');
                $table->decimal('quantity', 12, 4);
                $table->decimal('qty_invoiced', 12, 4)->default(0);
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();
            });
        }
    }

    private function createInvoicePayments(): void
    {
        if (Schema::hasTable('invoice_payments')) {
            return;
        }
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('bank_account_code', 20);
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['invoice_id', 'payment_date']);
        });
    }

    private function createInvoiceAttachments(): void
    {
        if (Schema::hasTable('invoice_attachments')) {
            return;
        }
        Schema::create('invoice_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime', 120)->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
        });
    }

    private function createArDeposits(): void
    {
        if (Schema::hasTable('ar_deposits')) {
            return;
        }
        Schema::create('ar_deposits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->constrained();
            $table->decimal('amount', 15, 2);
            $table->decimal('applied_amount', 15, 2)->default(0);
            $table->date('payment_date');
            $table->string('bank_account_code', 20);
            $table->string('reference')->nullable();
            $table->string('status')->default('open');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        if (! Schema::hasTable('ar_deposit_applications')) {
            Schema::create('ar_deposit_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ar_deposit_id')->constrained('ar_deposits')->cascadeOnDelete();
                $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
                $table->decimal('amount', 15, 2);
                $table->timestamps();
            });
        }
    }

    private function createConsolidatedEInvoices(): void
    {
        if (Schema::hasTable('consolidated_e_invoices')) {
            return;
        }
        Schema::create('consolidated_e_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('document_number');
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('status')->default('draft');
            $table->string('lhdn_status')->default('pending');
            $table->string('lhdn_uuid')->nullable();
            $table->string('lhdn_long_id', 80)->nullable();
            $table->timestamp('lhdn_submitted_at')->nullable();
            $table->timestamp('lhdn_cancelled_at')->nullable();
            $table->text('lhdn_reject_reason')->nullable();
            $table->string('lhdn_qr_url', 500)->nullable();
            $table->timestamps();
        });
        Schema::create('consolidated_e_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consolidated_e_invoice_id')->constrained('consolidated_e_invoices')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['consolidated_e_invoice_id', 'invoice_id'], 'consol_einvoice_invoice_unique');
        });
    }

    private function ensureCustomerDepositsAccount(): void
    {
        if (! Schema::hasTable('accounts')) {
            return;
        }
        if (DB::table('accounts')->where('code', '2250')->exists()) {
            return;
        }
        DB::table('accounts')->insert([
            'code'          => '2250',
            'name'          => 'Customer Deposits',
            'type'          => 'liability',
            'sub_type'      => null,
            'parent_id'     => null,
            'description'   => 'Unapplied customer deposits / AR credits waiting to knock off invoices.',
            'is_active'     => true,
            'display_order' => 7,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function alterRecurringInvoices(): void
    {
        if (! Schema::hasTable('recurring_invoices')) {
            return;
        }
        Schema::table('recurring_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('recurring_invoices', 'auto_email')) {
                $table->boolean('auto_email')->default(false);
            }
        });
    }
};
