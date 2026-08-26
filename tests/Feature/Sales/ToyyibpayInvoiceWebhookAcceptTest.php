<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\ToyyibpayService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ToyyibpayInvoiceWebhookAcceptTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_toyyibpay_callback_marks_invoice_paid(): void
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
            'name' => 'Webhook Customer', 'code' => 'C-WH', 'billing_country' => 'Malaysia', 'is_active' => true,
        ]);
        $invoices = app(InvoiceService::class);
        $invoice = $invoices->create([
            'invoice_number' => 'INV-WH-001', 'msic_code' => '70200', 'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(),
            'currency' => 'MYR', 'shipping_amount' => 0,
        ], [[
            'description' => 'Webhook test', 'quantity' => 1, 'unit_price' => 75,
            'tax_rate' => 0, 'discount_amount' => 0, 'item_classification' => '022',
        ]]);
        $invoices->post($invoice);
        tenancy()->end();

        $ref = 'inv-'.$invoice->id.'-'.$tenant->id;
        $stub = Mockery::mock(ToyyibpayService::class);
        $stub->shouldReceive('verifyPaidBill')->once()->with('good-bill', $ref)->andReturn(true);
        $this->app->instance(ToyyibpayService::class, $stub);

        $response = $this->post('/pay/toyyibpay/callback', [
            'billcode'  => 'good-bill',
            'status_id' => 1,
            'order_id'  => $ref,
        ]);

        $response->assertOk();
        tenancy()->initialize($tenant);
        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
