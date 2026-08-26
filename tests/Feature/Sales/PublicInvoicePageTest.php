<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicInvoicePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    public function test_signed_public_html_invoice_page_loads(): void
    {
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
            'name' => 'Public Customer', 'code' => 'C-1', 'billing_country' => 'Malaysia', 'is_active' => true,
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-PUB-001',
            'customer_id'    => $customer->id,
            'issue_date'     => now()->toDateString(),
            'due_date'       => now()->addDays(14)->toDateString(),
            'amount_before_tax'=> 100,
            'tax_amount'     => 0,
            'total_amount'   => 100,
            'amount_paid'    => 0,
            'status'         => 'unpaid',
            'currency'       => 'MYR',
        ]);
        $invoice->items()->create([
            'description' => 'Consulting',
            'quantity'    => 1,
            'unit_price'  => 100,
            'tax_rate'    => 0,
            'amount'      => 100,
        ]);
        tenancy()->end();

        $url = URL::temporarySignedRoute('public.invoices.show', now()->addHour(), [
            'uuid'      => $invoice->uuid,
            'tenant_id' => $tenant->id,
        ]);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('INV-PUB-001');
        $response->assertSee('100.00');
    }

    public function test_unsigned_public_html_invoice_is_forbidden(): void
    {
        $response = $this->get('/pay/invoice/'.(string) \Illuminate\Support\Str::uuid());

        $response->assertForbidden();
    }
}
