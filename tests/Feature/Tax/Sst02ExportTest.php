<?php

namespace Tests\Feature\Tax;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use App\Support\TaxCodeDefaults;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sst02ExportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);

        $this->tenant = $this->createTenantWithDatabase();
        $this->tenant->forceFill([
            'provision_status' => 'ready',
            'provisioned_at'   => now(),
        ])->save();

        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id'   => Plan::where('slug', 'growth')->firstOrFail()->id,
            'status'    => 'active',
            'interval'  => 'lifetime',
            'gateway'   => 'system',
        ]);

        $adminRole = Role::where('name', 'admin')->first();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $adminRole?->id,
        ]);
        if ($adminRole) {
            $this->user->assignRole('admin');
        }

        tenancy()->initialize($this->tenant);
        TaxCodeDefaults::seedMissing();

        $this->customer = Customer::create([
            'name'            => 'SST Customer',
            'code'            => 'CUST-SST-001',
            'email'           => 'sst@test.com',
            'billing_country' => 'Malaysia',
            'is_active'       => true,
        ]);
    }

    public function test_sst02_csv_includes_sr8_row_after_posted_invoice_with_tax(): void
    {
        $issueDate = now()->toDateString();
        $invoices = app(InvoiceService::class);

        $invoice = $invoices->create([
            'invoice_number'  => 'INV-SST-001',
            'msic_code'       => '70200',
            'customer_id'     => $this->customer->id,
            'issue_date'      => $issueDate,
            'due_date'        => now()->addDays(30)->toDateString(),
            'currency'        => 'MYR',
            'shipping_amount' => 0,
        ], [[
            'description'         => 'Taxable supply',
            'quantity'            => 1,
            'unit_price'          => 100,
            'tax_rate'            => 8,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);

        $invoices->post($invoice);
        tenancy()->end();

        $response = $this->actingAs($this->user)->get(route('reports.sales-tax.export-sst02', [
            'start_date' => $issueDate,
            'end_date'   => $issueDate,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('SR-8', $csv);
        $this->assertStringContainsString('tax_code', $csv);
        $this->assertStringContainsString('100', $csv);
        $this->assertStringContainsString('8', $csv);
    }
}
