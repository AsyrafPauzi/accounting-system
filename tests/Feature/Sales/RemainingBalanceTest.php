<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CreditNoteService;
use App\Services\InvoiceService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemainingBalanceTest extends TestCase
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
        $plan = Plan::where('slug', 'corporate')->firstOrFail();
        Subscription::create([
            'tenant_id'              => $this->tenant->id,
            'plan_id'                => $plan->id,
            'status'                 => 'active',
            'interval'               => 'lifetime',
            'current_period_start'   => now(),
            'gateway'                => 'system',
        ]);

        User::factory()->create(['tenant_id' => $this->tenant->id]);

        tenancy()->initialize($this->tenant);
        $this->customer = Customer::create([
            'name'            => 'Aging Customer',
            'code'            => 'CUST-AG-001',
            'email'           => 'aging@test.com',
            'billing_country' => 'Malaysia',
            'is_active'       => true,
        ]);
        $this->invoices = app(InvoiceService::class);
    }

    public function test_remaining_balance_subtracts_applied_credit_note(): void
    {
        $invoice = $this->createPostedInvoice(1000.0);

        app(CreditNoteService::class)->issue([
            'invoice_id'         => $invoice->id,
            'customer_id'        => $this->customer->id,
            'cn_number'          => 'CN-AG-001',
            'issue_date'         => now()->toDateString(),
            'reason_code'        => '03',
            'reason_description' => 'Partial credit',
            'currency'           => 'MYR',
        ], [[
            'description'         => 'Goodwill credit',
            'quantity'            => 1,
            'unit_price'          => 250,
            'tax_rate'            => 0,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);

        $invoice->refresh();
        $this->assertSame(750.0, $this->invoices->remainingBalance($invoice));
        $this->assertSame(750.0, $invoice->balance_due);
    }

    public function test_customer_balance_uses_remaining_balance_after_credit(): void
    {
        $invoice = $this->createPostedInvoice(1000.0);

        app(CreditNoteService::class)->issue([
            'invoice_id'         => $invoice->id,
            'customer_id'        => $this->customer->id,
            'cn_number'          => 'CN-AG-002',
            'issue_date'         => now()->toDateString(),
            'reason_code'        => '03',
            'reason_description' => 'Partial credit',
            'currency'           => 'MYR',
        ], [[
            'description'         => 'Goodwill credit',
            'quantity'            => 1,
            'unit_price'          => 200,
            'tax_rate'            => 0,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);

        $this->assertSame(800.0, $this->customer->fresh()->balance);
        $this->assertSame(800.0, $this->invoices->sumOutstanding());
    }

    public function test_remaining_balance_subtracts_applied_ar_deposit(): void
    {
        $invoice = $this->createPostedInvoice(1000.0);

        $deposit = app(\App\Services\ArDepositService::class)->receive([
            'customer_id'       => $this->customer->id,
            'amount'            => 300,
            'payment_date'      => now()->toDateString(),
            'bank_account_code' => '1200',
            'reference'         => 'DEP-TEST',
        ]);

        app(\App\Services\ArDepositService::class)->applyToInvoice($deposit, $invoice, 300);

        $invoice->refresh();
        $this->assertSame(700.0, $this->invoices->remainingBalance($invoice));
        $this->assertSame(700.0, $invoice->balance_due);
    }

    public function test_bill_remaining_balance_subtracts_supplier_credit_note(): void
    {
        $supplier = \App\Models\Supplier::create([
            'name'            => 'AP Supplier',
            'code'            => 'SUP-001',
            'billing_country' => 'Malaysia',
            'is_active'       => true,
        ]);

        $bills = app(\App\Services\BillService::class);
        $bill = $bills->create([
            'bill_number' => 'BILL-AG-'.uniqid('', true),
            'supplier_id' => $supplier->id,
            'bill_date'   => now()->toDateString(),
            'due_date'    => now()->addDays(30)->toDateString(),
        ], [[
            'account_code' => '5000',
            'description'  => 'Office supplies',
            'amount'       => 800,
        ]]);
        $bills->post($bill);

        app(\App\Services\SupplierCreditNoteService::class)->issue([
            'bill_id'            => $bill->id,
            'supplier_id'        => $supplier->id,
            'scn_number'         => 'SCN-AG-001',
            'issue_date'         => now()->toDateString(),
            'reason_description' => 'Partial credit',
            'currency'           => 'MYR',
        ], [[
            'account_code'    => '5000',
            'description'     => 'Return',
            'quantity'        => 1,
            'unit_price'      => 150,
            'tax_rate'        => 0,
            'discount_amount' => 0,
        ]]);

        $bill->refresh();
        $this->assertSame(650.0, $bills->remainingBalance($bill));
    }

    private function createPostedInvoice(float $unitPrice): \App\Models\Invoice
    {
        $invoice = $this->invoices->create([
            'invoice_number'  => 'INV-AG-'.uniqid('', true),
            'msic_code'       => '70200',
            'customer_id'     => $this->customer->id,
            'issue_date'      => now()->subDays(20)->toDateString(),
            'due_date'        => now()->subDays(10)->toDateString(),
            'currency'        => 'MYR',
            'shipping_amount' => 0,
        ], [[
            'description'         => 'Consulting services',
            'quantity'            => 1,
            'unit_price'          => $unitPrice,
            'tax_rate'            => 0,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);
        $this->invoices->post($invoice);

        return $invoice->fresh(['items', 'customer']);
    }
}
