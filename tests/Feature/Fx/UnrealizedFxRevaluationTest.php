<?php

namespace Tests\Feature\Fx;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\FxGainLossService;
use App\Services\FxRevaluationService;
use App\Services\InvoiceService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UnrealizedFxRevaluationTest extends TestCase
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
            'name'            => 'FX Reval Customer',
            'code'            => 'CUST-FXR-001',
            'email'           => 'fxreval@test.com',
            'billing_country' => 'Malaysia',
            'is_active'       => true,
        ]);
        $this->invoices = app(InvoiceService::class);
    }

    public function test_open_usd_invoice_posts_unrealized_fx_gain_at_month_end(): void
    {
        $invoice = $this->invoices->create([
            'invoice_number'  => 'INV-FXR-001',
            'msic_code'       => '70200',
            'customer_id'     => $this->customer->id,
            'issue_date'      => '2026-07-01',
            'due_date'        => '2026-07-31',
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

        $posted = app(FxRevaluationService::class)->revaluateAll('2026-07-31', ['USD' => 4.80]);

        $this->assertCount(1, $posted);
        $this->assertSame('invoice', $posted[0]['kind']);
        $this->assertSame(30.0, $posted[0]['adjustment']);

        $gainCredit = (float) DB::table('journal_items')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->where('journal_entries.reference_type', FxRevaluationService::REFERENCE_TYPE_INVOICE)
            ->where('journal_entries.reference_id', $invoice->id)
            ->where('journal_items.account_code', FxGainLossService::GAIN_ACCOUNT)
            ->value('journal_items.credit');

        $this->assertSame(30.0, $gainCredit);
    }

    public function test_revaluation_is_idempotent_for_same_month(): void
    {
        $invoice = $this->invoices->create([
            'invoice_number'  => 'INV-FXR-002',
            'msic_code'       => '70200',
            'customer_id'     => $this->customer->id,
            'issue_date'      => '2026-07-01',
            'due_date'        => '2026-07-31',
            'currency'        => 'USD',
            'exchange_rate'   => 4.50,
            'shipping_amount' => 0,
        ], [[
            'description'         => 'USD services',
            'quantity'            => 1,
            'unit_price'          => 50.0,
            'tax_rate'            => 0,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);

        $this->invoices->post($invoice);

        $service = app(FxRevaluationService::class);
        $first = $service->revaluateAll('2026-07-31', ['USD' => 4.80]);
        $second = $service->revaluateAll('2026-07-31', ['USD' => 4.80]);

        $this->assertCount(1, $first);
        $this->assertCount(0, $second);
    }

    public function test_fx_revaluate_command_posts_for_tenant(): void
    {
        $invoice = $this->invoices->create([
            'invoice_number'  => 'INV-FXR-003',
            'msic_code'       => '70200',
            'customer_id'     => $this->customer->id,
            'issue_date'      => '2026-07-01',
            'due_date'        => '2026-07-31',
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

        $exit = Artisan::call('fx:revaluate', [
            '--month' => '2026-07',
            '--rates' => ['USD:4.80'],
            '--tenants' => [$this->tenant->id],
        ]);

        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => FxRevaluationService::REFERENCE_TYPE_INVOICE,
            'reference_id' => $invoice->id,
        ]);
    }

    public function test_open_usd_bill_posts_unrealized_fx_loss_at_month_end(): void
    {
        $supplier = \App\Models\Supplier::create([
            'name'            => 'FX Reval Supplier',
            'code'            => 'SUP-FXR-001',
            'billing_country' => 'Malaysia',
            'is_active'       => true,
        ]);

        $bills = app(\App\Services\BillService::class);
        $bill = $bills->create([
            'bill_number'   => 'BILL-FXR-001',
            'supplier_id'   => $supplier->id,
            'bill_date'     => '2026-07-01',
            'due_date'      => '2026-07-31',
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

        $posted = app(FxRevaluationService::class)->revaluateAll('2026-07-31', ['USD' => 4.80]);

        $this->assertCount(1, $posted);
        $this->assertSame('bill', $posted[0]['kind']);

        $lossDebit = (float) DB::table('journal_items')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->where('journal_entries.reference_type', FxRevaluationService::REFERENCE_TYPE_BILL)
            ->where('journal_entries.reference_id', $bill->id)
            ->where('journal_items.account_code', FxGainLossService::LOSS_ACCOUNT)
            ->value('journal_items.debit');

        $this->assertSame(30.0, $lossDebit);
    }
}
