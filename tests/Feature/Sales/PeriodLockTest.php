<?php

namespace Tests\Feature\Sales;

use App\Models\AccountingPeriod;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\InvoiceService;
use App\Support\AccountingPeriodResolver;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodLockTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Customer $customer;

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
        AccountingPeriodResolver::ensurePeriodsExist();
        $this->customer = Customer::create([
            'name' => 'Period Customer', 'code' => 'C-PER', 'billing_country' => 'Malaysia', 'is_active' => true,
        ]);
    }

    public function test_post_invoice_in_closed_period_is_rejected(): void
    {
        $issueDate = now()->startOfMonth()->toDateString();
        AccountingPeriod::query()
            ->whereDate('start_date', '<=', $issueDate)
            ->whereDate('end_date', '>=', $issueDate)
            ->update(['status' => 'closed', 'closed_at' => now()]);

        $invoice = app(InvoiceService::class)->create([
            'invoice_number' => 'INV-CLOSED-001',
            'msic_code'      => '70200',
            'customer_id'    => $this->customer->id,
            'issue_date'     => $issueDate,
            'due_date'       => now()->addDays(30)->toDateString(),
            'currency'       => 'MYR',
            'shipping_amount'=> 0,
        ], [[
            'description' => 'Blocked post', 'quantity' => 1, 'unit_price' => 50,
            'tax_rate' => 0, 'discount_amount' => 0, 'item_classification' => '022',
        ]]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('closed');

        app(InvoiceService::class)->post($invoice);
    }
}
