<?php

namespace Tests\Feature\Tax;

use App\Http\Controllers\SalesTaxReportController;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Services\InvoiceService;
use App\Support\TaxCodeDefaults;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class SalesTaxReportCnDnTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_tax_report_groups_by_tax_code(): void
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
        TaxCodeDefaults::seedMissing();
        $sr8 = TaxCode::query()->where('code', 'SR-8')->firstOrFail();

        $customer = Customer::create([
            'name' => 'SST Customer', 'code' => 'C-SST', 'billing_country' => 'Malaysia', 'is_active' => true,
        ]);

        $invoices = app(InvoiceService::class);
        $invoice = $invoices->create([
            'invoice_number' => 'INV-SST-001',
            'customer_id'    => $customer->id,
            'issue_date'     => now()->toDateString(),
            'due_date'       => now()->addDays(30)->toDateString(),
            'msic_code'      => '70200',
        ], [[
            'description' => 'Taxable',
            'quantity'    => 1,
            'unit_price'  => 100,
            'tax_code_id' => $sr8->id,
            'tax_rate'    => 8,
            'item_classification' => '011',
        ]]);
        $invoices->post($invoice);

        $controller = app(SalesTaxReportController::class);
        $method = new ReflectionMethod($controller, 'buildReportData');
        $method->setAccessible(true);
        $data = $method->invoke($controller, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());

        $this->assertNotEmpty($data['by_code']);
        $sr8Row = collect($data['by_code'])->firstWhere('tax_code', 'SR-8');
        $this->assertNotNull($sr8Row);
        $this->assertSame(100.0, $sr8Row['taxable']);
        $this->assertSame(8.0, $data['output_tax']);
    }
}
