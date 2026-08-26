<?php

namespace Tests\Feature\Fx;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FxGainLossService;
use App\Services\InvoiceService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RealizedFxOnPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Customer $customer;

    private InvoiceService $invoices;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);

        $this->tenant = $this->createTenantWithDatabase();
        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id'   => Plan::where('slug', 'corporate')->firstOrFail()->id,
            'status'    => 'active',
            'interval'  => 'lifetime',
            'gateway'   => 'system',
        ]);

        tenancy()->initialize($this->tenant);
        $this->customer = Customer::create([
            'name'            => 'FX Customer',
            'code'            => 'CUST-FX-001',
            'email'           => 'fx@test.com',
            'billing_country' => 'Malaysia',
            'is_active'       => true,
        ]);
        $this->invoices = app(InvoiceService::class);
    }

    public function test_usd_invoice_payment_at_higher_rate_posts_fx_gain(): void
    {
        $invoice = $this->invoices->create([
            'invoice_number'  => 'INV-FX-001',
            'msic_code'       => '70200',
            'customer_id'     => $this->customer->id,
            'issue_date'      => now()->toDateString(),
            'due_date'        => now()->addDays(30)->toDateString(),
            'currency'        => 'USD',
            'exchange_rate'   => 4.50,
            'shipping_amount' => 0,
        ], [[
            'description'         => 'USD services',
            'quantity'            => 1,
            'unit_price'          => 100.0,
            'tax_rate'            => 0,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);

        $this->invoices->post($invoice);

        $payment = $this->invoices->recordPayment(
            $invoice->fresh(),
            100.0,
            now()->toDateString(),
            '1200',
            'FX-PAY-001',
            null,
            4.80,
        );

        $gainCredit = (float) DB::table('journal_items')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->where('journal_entries.reference_type', 'Invoice Payment')
            ->where('journal_entries.reference_id', $payment->id)
            ->where('journal_items.account_code', FxGainLossService::GAIN_ACCOUNT)
            ->value('journal_items.credit');

        $this->assertSame(30.0, $gainCredit);
    }

    public function test_usd_bill_payment_at_higher_rate_posts_fx_loss(): void
    {
        $supplier = \App\Models\Supplier::create([
            'name'            => 'FX Supplier',
            'code'            => 'SUP-FX-001',
            'billing_country' => 'Malaysia',
            'is_active'       => true,
        ]);

        $bills = app(\App\Services\BillService::class);
        $bill = $bills->create([
            'bill_number'   => 'BILL-FX-001',
            'supplier_id'   => $supplier->id,
            'bill_date'     => now()->toDateString(),
            'due_date'      => now()->addDays(30)->toDateString(),
            'currency'      => 'USD',
            'exchange_rate' => 4.50,
        ], [[
            'account_code' => '5000',
            'description'  => 'USD purchase',
            'quantity'     => 1,
            'unit_amount'  => 100.0,
            'amount'       => 100.0,
        ]]);

        $bills->post($bill);

        $payment = $bills->recordPayment(
            $bill->fresh(),
            100.0,
            now()->toDateString(),
            '1200',
            'FX-BILL-PAY-001',
            null,
            4.80,
        );

        $lossDebit = (float) DB::table('journal_items')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->where('journal_entries.reference_type', 'Bill Payment')
            ->where('journal_entries.reference_id', $payment->id)
            ->where('journal_items.account_code', FxGainLossService::LOSS_ACCOUNT)
            ->value('journal_items.debit');

        $this->assertSame(30.0, $lossDebit);
    }
}
