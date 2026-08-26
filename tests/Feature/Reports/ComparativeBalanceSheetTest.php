<?php

namespace Tests\Feature\Reports;

use App\Http\Controllers\BalanceSheetController;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class ComparativeBalanceSheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_sheet_compare_includes_prior_period_amounts(): void
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
            'name' => 'Compare BS', 'code' => 'C-CMP', 'billing_country' => 'Malaysia', 'is_active' => true,
        ]);

        $invoices = app(InvoiceService::class);
        $invoice = $invoices->create([
            'invoice_number' => 'INV-CMP-001',
            'customer_id'    => $customer->id,
            'issue_date'     => now()->toDateString(),
            'due_date'       => now()->addDays(30)->toDateString(),
            'msic_code'      => '70200',
        ], [[
            'description' => 'Sale', 'quantity' => 1, 'unit_price' => 100,
            'tax_rate' => 0, 'item_classification' => '011',
        ]]);
        $invoices->post($invoice);

        $asAt = now()->toDateString();
        $controller = app(BalanceSheetController::class);
        $method = new ReflectionMethod($controller, 'buildComparedBalanceSheetData');
        $method->setAccessible(true);
        $data = $method->invoke($controller, $asAt, 'previous');

        $this->assertNotNull($data['compare_as_at_date']);
        $this->assertSame(
            Carbon::parse($asAt)->subMonthNoOverflow()->endOfMonth()->toDateString(),
            $data['compare_as_at_date'],
        );

        $arLine = collect($data['asset_accounts'])->firstWhere('code', '1100');
        $this->assertNotNull($arLine);
        $this->assertSame(100.0, (float) $arLine['amount']);
        $this->assertArrayHasKey('compare_amount', $arLine);
        $this->assertArrayHasKey('variance', $arLine);
    }
}
