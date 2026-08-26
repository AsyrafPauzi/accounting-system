<?php

namespace Tests\Feature\Reports;

use App\Http\Controllers\BalanceSheetController;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class BalanceSheetBalancingTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_sheet_balances_after_posted_sale(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);

        $tenant = $this->createTenantWithDatabase();
        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id'   => Plan::where('slug', 'corporate')->firstOrFail()->id,
            'status'    => 'active',
            'interval'  => 'lifetime',
            'gateway'   => 'system',
        ]);

        tenancy()->initialize($tenant);

        $customer = Customer::create([
            'name' => 'BS Customer', 'code' => 'C-BS', 'billing_country' => 'Malaysia', 'is_active' => true,
        ]);

        $invoices = app(InvoiceService::class);
        $invoice = $invoices->create([
            'invoice_number' => 'INV-BS-001',
            'customer_id'    => $customer->id,
            'issue_date'     => now()->toDateString(),
            'due_date'       => now()->addDays(30)->toDateString(),
            'msic_code'      => '70200',
        ], [[
            'description' => 'Sale', 'quantity' => 1, 'unit_price' => 100,
            'tax_rate' => 0, 'item_classification' => '011',
        ]]);
        $invoices->post($invoice);

        $controller = app(BalanceSheetController::class);
        $method = new ReflectionMethod($controller, 'buildBalanceSheetData');
        $method->setAccessible(true);
        $data = $method->invoke($controller, now()->toDateString());

        $assets = (float) $data['total_assets'];
        $liabilitiesEquity = (float) $data['total_liabilities'] + (float) $data['total_equity'];

        $this->assertTrue(abs($assets - $liabilitiesEquity) < 0.02, "Assets {$assets} != L+E {$liabilitiesEquity}");
        $this->assertTrue($data['balanced']);
    }
}
