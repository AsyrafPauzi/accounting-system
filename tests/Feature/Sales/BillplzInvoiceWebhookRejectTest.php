<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillplzInvoiceWebhookRejectTest extends TestCase
{
    use RefreshDatabase;

    public function test_billplz_invoice_callback_rejects_when_tenant_not_configured(): void
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
            'name' => 'Billplz Customer', 'code' => 'C-BP', 'billing_country' => 'Malaysia', 'is_active' => true,
        ]);
        $invoices = app(InvoiceService::class);
        $invoice = $invoices->create([
            'invoice_number' => 'INV-BP-001', 'msic_code' => '70200', 'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(),
            'currency' => 'MYR', 'shipping_amount' => 0,
        ], [[
            'description' => 'Billplz test', 'quantity' => 1, 'unit_price' => 90,
            'tax_rate' => 0, 'discount_amount' => 0, 'item_classification' => '022',
        ]]);
        $invoices->post($invoice);
        tenancy()->end();

        $ref = 'inv-'.$invoice->id.'-'.$tenant->id;

        $response = $this->post('/pay/billplz/callback', [
            'reference_1' => $ref,
            'id'          => 'fake-bill-id',
            'paid'        => 'true',
        ]);

        $response->assertOk();
        $response->assertSee('unpaid');
        tenancy()->initialize($tenant);
        $this->assertSame('unpaid', $invoice->fresh()->status);
    }
}
