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
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillplzInvoiceWebhookApiFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_billplz_invoice_callback_settles_when_signature_wrong_but_api_says_paid(): void
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

        $tenant->forceFill([
            'billplz_secret_key'     => encrypt('api-secret'),
            'billplz_collection_id'  => 'col-1',
            'billplz_xsignature_key' => encrypt('wrong-xsig'),
            'billplz_sandbox'        => true,
            'invoice_gateway'        => 'billplz',
        ])->save();

        tenancy()->initialize($tenant);
        $customer = Customer::create([
            'name' => 'Billplz Fallback', 'code' => 'C-BF', 'billing_country' => 'Malaysia', 'is_active' => true,
        ]);
        $invoices = app(InvoiceService::class);
        $invoice = $invoices->create([
            'invoice_number' => 'INV-BF-001', 'msic_code' => '70200', 'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(),
            'currency' => 'MYR', 'shipping_amount' => 0,
        ], [[
            'description' => 'Fallback test', 'quantity' => 1, 'unit_price' => 50,
            'tax_rate' => 0, 'discount_amount' => 0, 'item_classification' => '022',
        ]]);
        $invoices->post($invoice);
        tenancy()->end();

        $billId = '6aad9a6a7ff348be';
        $ref = 'inv-'.$invoice->id.'-'.$tenant->id;

        Http::fake([
            'www.billplz-sandbox.com/api/v3/bills/'.$billId => Http::response([
                'id'    => $billId,
                'paid'  => true,
                'state' => 'paid',
            ], 200),
        ]);

        $response = $this->post('/pay/billplz/callback', [
            'id'          => $billId,
            'reference_1' => $ref,
            'paid'        => 'true',
            'x_signature' => 'definitely-wrong',
        ]);

        $response->assertOk();
        $response->assertSee('ok');

        tenancy()->initialize($tenant);
        $this->assertSame('paid', $invoice->fresh()->status);
    }
}
