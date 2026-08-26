<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\CustomerPortalService;
use App\Services\InvoiceService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
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
        $this->customer = Customer::create([
            'name'            => 'Portal Customer',
            'code'            => 'C-PORTAL',
            'email'           => 'portal@test.com',
            'billing_country' => 'Malaysia',
            'is_active'       => true,
        ]);
    }

    public function test_signed_portal_dashboard_lists_customer_invoices(): void
    {
        $invoices = app(InvoiceService::class);
        $invoice = $invoices->create([
            'invoice_number'  => 'INV-PORTAL-001',
            'msic_code'       => '70200',
            'customer_id'     => $this->customer->id,
            'issue_date'      => now()->toDateString(),
            'due_date'        => now()->addDays(30)->toDateString(),
            'currency'        => 'MYR',
            'shipping_amount' => 0,
        ], [[
            'description'         => 'Portal test',
            'quantity'            => 1,
            'unit_price'          => 250,
            'tax_rate'            => 0,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);
        $invoices->post($invoice);

        $portal = app(CustomerPortalService::class);
        $token = $portal->issueToken($this->customer);
        tenancy()->end();

        $url = URL::temporarySignedRoute('portal.dashboard', now()->addHour(), [
            'token'     => $token->token,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('INV-PORTAL-001');
        $response->assertSee('Portal Customer');
        $response->assertSee('250.00');
    }

    public function test_expired_portal_token_is_forbidden(): void
    {
        $portal = app(CustomerPortalService::class);
        $token = $portal->issueToken($this->customer);
        $token->update(['expires_at' => now()->subDay()]);
        tenancy()->end();

        $url = URL::temporarySignedRoute('portal.dashboard', now()->addHour(), [
            'token'     => $token->token,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->get($url);

        $response->assertForbidden();
    }

    public function test_portal_statement_pdf_downloads(): void
    {
        $portal = app(CustomerPortalService::class);
        $token = $portal->issueToken($this->customer);
        tenancy()->end();

        $url = URL::temporarySignedRoute('portal.statement.pdf', now()->addHour(), [
            'token'     => $token->token,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
