<?php

namespace Tests\Feature\Reports;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\BudgetVsActualService;
use App\Services\InvoiceService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetVsActualTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Customer $customer;

    private BudgetVsActualService $budgets;

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
            'name'            => 'Budget Customer',
            'code'            => 'C-BUDGET',
            'billing_country' => 'Malaysia',
            'is_active'       => true,
        ]);
        $this->budgets = app(BudgetVsActualService::class);
    }

    public function test_budget_vs_actual_shows_variance_for_revenue(): void
    {
        $year = (int) now()->format('Y');
        $month = (int) now()->format('n');
        $dateFrom = now()->startOfMonth()->toDateString();
        $dateTo = now()->endOfMonth()->toDateString();

        $budget = $this->budgets->ensureBudgetForYear($year);
        $this->budgets->upsertLines($budget, [[
            'account_code' => '4000',
            'month'        => $month,
            'amount'       => 10000,
        ]]);

        $invoices = app(InvoiceService::class);
        $invoice = $invoices->create([
            'invoice_number'  => 'INV-BUD-001',
            'msic_code'       => '70200',
            'customer_id'     => $this->customer->id,
            'issue_date'      => $dateFrom,
            'due_date'        => now()->addDays(30)->toDateString(),
            'currency'        => 'MYR',
            'shipping_amount' => 0,
        ], [[
            'description'         => 'Budget test sale',
            'quantity'            => 1,
            'unit_price'          => 5000,
            'tax_rate'            => 0,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);
        $invoices->post($invoice);

        $report = $this->budgets->build($budget, $dateFrom, $dateTo);

        $revenueRow = collect($report['revenue_rows'])->firstWhere('code', '4000');
        $this->assertNotNull($revenueRow);
        $this->assertSame(10000.0, $revenueRow['budget']);
        $this->assertSame(5000.0, $revenueRow['actual']);
        $this->assertSame(-5000.0, $revenueRow['variance']);
        $this->assertSame(-50.0, $revenueRow['variance_pct']);
    }

    public function test_budget_entry_persists_monthly_amounts(): void
    {
        $year = (int) now()->format('Y');
        $budget = $this->budgets->ensureBudgetForYear($year);

        $this->budgets->upsertLines($budget, [
            ['account_code' => '5100', 'month' => 8, 'amount' => 15000],
            ['account_code' => '5100', 'month' => 9, 'amount' => 15000],
        ]);

        $this->assertDatabaseHas('budget_lines', [
            'budget_id'    => $budget->id,
            'account_code' => '5100',
            'month'        => 8,
            'amount'       => 15000,
        ]);

        $report = $this->budgets->build($budget, "{$year}-08-01", "{$year}-08-31");
        $expenseRow = collect($report['expense_rows'])->firstWhere('code', '5100');
        $this->assertNotNull($expenseRow);
        $this->assertSame(15000.0, $expenseRow['budget']);
        $this->assertSame(0.0, $expenseRow['actual']);
    }
}
