<?php

namespace Tests\Feature\Reports;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitAndLossSourcesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);

        $this->tenant = $this->createTenantWithDatabase();
        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => Plan::where('slug', 'corporate')->firstOrFail()->id,
            'status' => 'active',
            'interval' => 'lifetime',
            'gateway' => 'system',
        ]);

        $adminRole = Role::where('name', 'admin')->first();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $adminRole?->id,
        ]);
        if ($adminRole) {
            $this->user->assignRole('admin');
        }

        tenancy()->initialize($this->tenant);

        $customer = Customer::create([
            'name' => 'PL Source Customer',
            'code' => 'CUST-PL-001',
            'email' => 'pl@test.com',
            'billing_country' => 'Malaysia',
            'is_active' => true,
        ]);

        $invoice = app(InvoiceService::class)->create([
            'invoice_number' => 'INV-PL-001',
            'msic_code' => '70200',
            'customer_id' => $customer->id,
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-31',
            'currency' => 'MYR',
            'exchange_rate' => 1,
            'shipping_amount' => 0,
        ], [[
            'description' => 'Consulting',
            'quantity' => 1,
            'unit_price' => 500.0,
            'tax_rate' => 0,
            'discount_amount' => 0,
            'item_classification' => '022',
        ]]);

        app(InvoiceService::class)->post($invoice);
    }

    public function test_pl_sources_page_lists_invoice_for_revenue_account(): void
    {
        $this->withoutVite();
        $response = $this->actingAs($this->user)->get(route('profit-and-loss.sources', [
            'account_code' => '4000',
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/ProfitAndLossSources')
            ->has('sources', 1)
            ->where('sources.0.reference_type', 'Invoice')
            ->where('sources.0.amount', 500)
        );
    }
}
