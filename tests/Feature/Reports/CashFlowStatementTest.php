<?php

namespace Tests\Feature\Reports;

use App\Http\Controllers\ProfitAndLossController;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\CashFlowStatementService;
use App\Services\InvoiceService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashFlowStatementTest extends TestCase
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
            'name'            => 'CF Customer',
            'code'            => 'CUST-CF-001',
            'billing_country' => 'Malaysia',
            'is_active'       => true,
        ]);
        $this->invoices = app(InvoiceService::class);
    }

    public function test_cash_flow_statement_reconciles_after_invoice_and_payment(): void
    {
        $today = now()->toDateString();
        $periodFrom = now()->startOfMonth()->toDateString();
        $periodTo = now()->endOfMonth()->toDateString();

        $invoice = $this->invoices->create([
            'invoice_number'  => 'INV-CF-001',
            'msic_code'       => '70200',
            'customer_id'     => $this->customer->id,
            'issue_date'      => $today,
            'due_date'        => now()->addDays(30)->toDateString(),
            'currency'        => 'MYR',
            'shipping_amount' => 0,
        ], [[
            'description'         => 'Consulting',
            'quantity'            => 1,
            'unit_price'          => 500.0,
            'tax_rate'            => 0,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);

        $this->invoices->post($invoice);
        $this->invoices->recordPayment($invoice->fresh(), 500.0, $today, '1200', 'CF-PAY-001');

        $pl = app(ProfitAndLossController::class)->buildPlDataPublic($periodFrom, $periodTo, 'accrual');
        $statement = app(CashFlowStatementService::class)->build($periodFrom, $periodTo, (float) $pl['net_profit']);

        $this->assertSame(500.0, $statement['net_profit']);
        $this->assertSame(500.0, $statement['closing_cash']);
        $this->assertTrue($statement['reconciled']);
    }

    public function test_cash_basis_pl_excludes_unpaid_invoice_revenue(): void
    {
        $today = now()->toDateString();
        $periodFrom = now()->startOfMonth()->toDateString();
        $periodTo = now()->endOfMonth()->toDateString();

        $invoice = $this->invoices->create([
            'invoice_number'  => 'INV-CF-002',
            'msic_code'       => '70200',
            'customer_id'     => $this->customer->id,
            'issue_date'      => $today,
            'due_date'        => now()->addDays(30)->toDateString(),
            'currency'        => 'MYR',
            'shipping_amount' => 0,
        ], [[
            'description'         => 'Unpaid work',
            'quantity'            => 1,
            'unit_price'          => 800.0,
            'tax_rate'            => 0,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);

        $this->invoices->post($invoice);

        $plController = app(ProfitAndLossController::class);
        $accrual = $plController->buildPlDataPublic($periodFrom, $periodTo, 'accrual');
        $cash = $plController->buildPlDataPublic($periodFrom, $periodTo, 'cash');

        $this->assertSame(800.0, $accrual['total_revenue']);
        $this->assertSame(0.0, $cash['total_revenue']);
    }

    public function test_cash_basis_pl_includes_revenue_when_paid(): void
    {
        $today = now()->toDateString();
        $periodFrom = now()->startOfMonth()->toDateString();
        $periodTo = now()->endOfMonth()->toDateString();

        $invoice = $this->invoices->create([
            'invoice_number'  => 'INV-CF-003',
            'msic_code'       => '70200',
            'customer_id'     => $this->customer->id,
            'issue_date'      => $today,
            'due_date'        => now()->addDays(30)->toDateString(),
            'currency'        => 'MYR',
            'shipping_amount' => 0,
        ], [[
            'description'         => 'Paid work',
            'quantity'            => 1,
            'unit_price'          => 350.0,
            'tax_rate'            => 0,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);

        $this->invoices->post($invoice);
        $this->invoices->recordPayment($invoice->fresh(), 350.0, $today, '1200');

        $cash = app(ProfitAndLossController::class)->buildPlDataPublic($periodFrom, $periodTo, 'cash');

        $this->assertSame(350.0, $cash['total_revenue']);
    }
}
