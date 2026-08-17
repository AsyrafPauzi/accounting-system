<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purchases (AP) parity: bill payment rows, PO → GRN → bill,
 * supplier CN/DN, prepaid deposits, recurring bills.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->alterBills();
        $this->createBillPayments();
        $this->createPurchaseOrders();
        $this->createGoodsReceipts();
        $this->createSupplierCreditNotes();
        $this->createSupplierDebitNotes();
        $this->createApDeposits();
        $this->createRecurringBills();
        $this->ensureSupplierPrepaymentsAccount();
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_bill_items');
        Schema::dropIfExists('recurring_bills');
        Schema::dropIfExists('ap_deposit_applications');
        Schema::dropIfExists('ap_deposits');
        Schema::dropIfExists('supplier_debit_note_items');
        Schema::dropIfExists('supplier_debit_notes');
        Schema::dropIfExists('supplier_credit_note_refunds');
        Schema::dropIfExists('supplier_credit_note_applications');
        Schema::dropIfExists('supplier_credit_note_items');
        Schema::dropIfExists('supplier_credit_notes');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('bill_payments');
    }

    private function alterBills(): void
    {
        if (! Schema::hasTable('bills')) {
            return;
        }
        Schema::table('bills', function (Blueprint $table) {
            if (! Schema::hasColumn('bills', 'purchase_order_id')) {
                $table->unsignedBigInteger('purchase_order_id')->nullable()->after('supplier_id');
            }
            if (! Schema::hasColumn('bills', 'goods_receipt_id')) {
                $table->unsignedBigInteger('goods_receipt_id')->nullable()->after('purchase_order_id');
            }
        });
    }

    private function createBillPayments(): void
    {
        if (Schema::hasTable('bill_payments')) {
            return;
        }
        Schema::create('bill_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('bank_account_code', 20);
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['bill_id', 'payment_date']);
        });
    }

    private function createPurchaseOrders(): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('po_number');
                $table->foreignId('supplier_id')->constrained();
                $table->date('issue_date');
                $table->date('expected_date')->nullable();
                $table->string('status')->default('confirmed');
                $table->string('currency', 3)->default('MYR');
                $table->decimal('exchange_rate', 18, 6)->default(1);
                $table->decimal('amount_before_tax', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['status', 'issue_date']);
            });
        }
        if (! Schema::hasTable('purchase_order_items')) {
            Schema::create('purchase_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('account_code', 20)->nullable();
                $table->string('description');
                $table->decimal('quantity', 12, 4);
                $table->decimal('qty_received', 12, 4)->default(0);
                $table->decimal('qty_billed', 12, 4)->default(0);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('amount', 15, 2);
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();
            });
        }
    }

    private function createGoodsReceipts(): void
    {
        if (! Schema::hasTable('goods_receipts')) {
            Schema::create('goods_receipts', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('grn_number');
                $table->foreignId('supplier_id')->constrained();
                $table->unsignedBigInteger('purchase_order_id')->nullable();
                $table->date('issue_date');
                $table->date('received_date')->nullable();
                $table->string('status')->default('received');
                $table->string('currency', 3)->default('MYR');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (! Schema::hasTable('goods_receipt_items')) {
            Schema::create('goods_receipt_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
                $table->unsignedBigInteger('purchase_order_item_id')->nullable();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('description');
                $table->decimal('quantity', 12, 4);
                $table->decimal('qty_billed', 12, 4)->default(0);
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();
            });
        }
    }

    private function createSupplierCreditNotes(): void
    {
        if (! Schema::hasTable('supplier_credit_notes')) {
            Schema::create('supplier_credit_notes', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('bill_id')->nullable();
                $table->foreignId('supplier_id')->constrained();
                $table->string('scn_number')->unique();
                $table->date('issue_date');
                $table->string('reason_code')->nullable();
                $table->text('reason_description')->nullable();
                $table->decimal('amount_before_tax', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->decimal('applied_amount', 15, 2)->default(0);
                $table->decimal('refunded_amount', 15, 2)->default(0);
                $table->string('currency', 3)->default('MYR');
                $table->string('status')->default('posted');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (! Schema::hasTable('supplier_credit_note_items')) {
            Schema::create('supplier_credit_note_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('supplier_credit_note_id');
                $table->foreign('supplier_credit_note_id', 'scn_items_scn_fk')->references('id')->on('supplier_credit_notes')->cascadeOnDelete();
                $table->string('account_code', 20)->nullable();
                $table->string('description');
                $table->decimal('quantity', 10, 2);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('amount', 15, 2);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('supplier_credit_note_applications')) {
            Schema::create('supplier_credit_note_applications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('supplier_credit_note_id');
                $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
                $table->decimal('amount', 15, 2);
                $table->timestamps();
                $table->foreign('supplier_credit_note_id', 'scn_apps_scn_fk')->references('id')->on('supplier_credit_notes')->cascadeOnDelete();
                $table->index(['bill_id', 'supplier_credit_note_id'], 'scn_apps_bill_scn_idx');
            });
        }
        if (! Schema::hasTable('supplier_credit_note_refunds')) {
            Schema::create('supplier_credit_note_refunds', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('supplier_credit_note_id');
                $table->foreign('supplier_credit_note_id', 'scn_refunds_scn_fk')->references('id')->on('supplier_credit_notes')->cascadeOnDelete();
                $table->decimal('amount', 15, 2);
                $table->date('payment_date');
                $table->string('bank_account_code', 20);
                $table->string('reference')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    private function createSupplierDebitNotes(): void
    {
        if (! Schema::hasTable('supplier_debit_notes')) {
            Schema::create('supplier_debit_notes', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('bill_id')->nullable();
                $table->foreignId('supplier_id')->constrained();
                $table->string('sdn_number')->unique();
                $table->date('issue_date');
                $table->string('reason_code')->nullable();
                $table->text('reason_description')->nullable();
                $table->decimal('amount_before_tax', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->string('currency', 3)->default('MYR');
                $table->string('status')->default('posted');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (! Schema::hasTable('supplier_debit_note_items')) {
            Schema::create('supplier_debit_note_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('supplier_debit_note_id');
                $table->foreign('supplier_debit_note_id', 'sdn_items_sdn_fk')->references('id')->on('supplier_debit_notes')->cascadeOnDelete();
                $table->string('account_code', 20)->nullable();
                $table->string('description');
                $table->decimal('quantity', 10, 2);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('amount', 15, 2);
                $table->timestamps();
            });
        }
    }

    private function createApDeposits(): void
    {
        if (! Schema::hasTable('ap_deposits')) {
            Schema::create('ap_deposits', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('supplier_id')->constrained();
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
        }
        if (! Schema::hasTable('ap_deposit_applications')) {
            Schema::create('ap_deposit_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ap_deposit_id')->constrained('ap_deposits')->cascadeOnDelete();
                $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
                $table->decimal('amount', 15, 2);
                $table->timestamps();
            });
        }
    }

    private function createRecurringBills(): void
    {
        if (! Schema::hasTable('recurring_bills')) {
            Schema::create('recurring_bills', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name')->nullable();
                $table->foreignId('supplier_id')->constrained();
                $table->string('cadence')->default('monthly');
                $table->unsignedSmallInteger('interval')->default(1);
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->date('next_run_date')->nullable();
                $table->date('last_run_date')->nullable();
                $table->unsignedBigInteger('last_generated_bill_id')->nullable();
                $table->unsignedInteger('generated_count')->default(0);
                $table->boolean('is_active')->default(true);
                $table->boolean('auto_post')->default(false);
                $table->unsignedSmallInteger('payment_terms_days')->default(30);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (! Schema::hasTable('recurring_bill_items')) {
            Schema::create('recurring_bill_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('recurring_bill_id');
                $table->foreign('recurring_bill_id', 'rb_items_rb_fk')->references('id')->on('recurring_bills')->cascadeOnDelete();
                $table->string('account_code', 20);
                $table->string('description');
                $table->decimal('quantity', 10, 2)->default(1);
                $table->decimal('unit_amount', 15, 2);
                $table->decimal('amount', 15, 2);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    private function ensureSupplierPrepaymentsAccount(): void
    {
        if (! Schema::hasTable('accounts')) {
            return;
        }
        if (DB::table('accounts')->where('code', '1300')->exists()) {
            return;
        }
        DB::table('accounts')->insert([
            'code'          => '1300',
            'name'          => 'Supplier Prepayments',
            'type'          => 'asset',
            'sub_type'      => null,
            'parent_id'     => null,
            'description'   => 'Deposits paid to suppliers waiting to knock off bills.',
            'is_active'     => true,
            'display_order' => 4,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
};
