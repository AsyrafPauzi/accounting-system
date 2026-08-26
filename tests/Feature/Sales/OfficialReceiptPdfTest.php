<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\InvoicePayment;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use App\Support\DocumentNumberDefaults;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficialReceiptPdfTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private InvoiceService $invoices;

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

        $adminRole = Role::where('name', 'admin')->first();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $adminRole?->id,
        ]);
        if ($adminRole) {
            $this->user->assignRole('admin');
        }

        tenancy()->initialize($this->tenant);
        DocumentNumberDefaults::seedMissing();

        $this->customer = Customer::create([
            'name'            => 'Receipt Customer',
            'code'            => 'CUST-OR-001',
            'email'           => 'receipt@test.com',
            'billing_country' => 'Malaysia',
            'is_active'       => true,
        ]);
        $this->invoices = app(InvoiceService::class);
    }

    public function test_payment_receipt_pdf_assigns_number_and_returns_pdf(): void
    {
        $invoice = $this->createPostedInvoice(150.0);
        $payment = $this->invoices->recordPayment(
            $invoice,
            150.0,
            now()->toDateString(),
            '1200',
            'OR-REF-001'
        );

        $this->assertNull($payment->receipt_number);
        tenancy()->end();

        $response = $this->actingAs($this->user)->get(route('invoices.payment-receipt', [
            $invoice->id,
            $payment->id,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());

        tenancy()->initialize($this->tenant);
        $payment->refresh();
        $this->assertNotNull($payment->receipt_number);
        $this->assertMatchesRegularExpression('/^OR-\d+/', $payment->receipt_number);

        $second = $this->actingAs($this->user)->get(route('invoices.payment-receipt', [
            $invoice->id,
            $payment->id,
        ]));
        $second->assertOk();
        $this->assertSame($payment->fresh()->receipt_number, InvoicePayment::find($payment->id)->receipt_number);
    }

    private function createPostedInvoice(float $unitPrice)
    {
        $invoice = $this->invoices->create([
            'invoice_number'  => 'INV-OR-'.uniqid('', true),
            'msic_code'       => '70200',
            'customer_id'     => $this->customer->id,
            'issue_date'      => now()->toDateString(),
            'due_date'        => now()->addDays(30)->toDateString(),
            'currency'        => 'MYR',
            'shipping_amount' => 0,
        ], [[
            'description'         => 'Consulting services',
            'quantity'            => 1,
            'unit_price'          => $unitPrice,
            'tax_rate'            => 0,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);
        $this->invoices->post($invoice);

        return $invoice->fresh(['items', 'customer']);
    }
}
