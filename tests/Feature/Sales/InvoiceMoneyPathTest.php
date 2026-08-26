<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\Invoice;
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

class InvoiceMoneyPathTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Customer $customer;

    private InvoiceService $invoices;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);

        $this->tenant = $this->createTenantWithDatabase();
        $plan = Plan::where('slug', 'corporate')->firstOrFail();
        Subscription::create([
            'tenant_id'              => $this->tenant->id,
            'plan_id'                => $plan->id,
            'status'                 => 'active',
            'interval'               => 'lifetime',
            'current_period_start'   => now(),
            'current_period_ends_at' => null,
            'gateway'                => 'system',
        ]);

        $adminRole = Role::where('name', 'admin')->first();
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $adminRole?->id,
        ]);
        if ($adminRole) {
            $user->assignRole('admin');
        }

        tenancy()->initialize($this->tenant);
        $this->customer = Customer::create([
            'name'            => 'Money Path Customer',
            'code'            => 'CUST-MP-001',
            'email'           => 'money-path@test.com',
            'billing_country' => 'Malaysia',
            'is_active'       => true,
        ]);
        $this->invoices = app(InvoiceService::class);
    }

    public function test_post_invoice_creates_posted_journal_and_unpaid_status(): void
    {
        $invoice = $this->createDraftInvoice();
        $this->invoices->post($invoice);

        $invoice->refresh();
        $this->assertSame('unpaid', $invoice->status);
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => 'Invoice',
            'reference_id'   => $invoice->id,
            'status'         => 'posted',
        ]);
    }

    public function test_record_payment_marks_invoice_paid(): void
    {
        $invoice = $this->createPostedInvoice(100.0);

        $this->invoices->recordPayment(
            $invoice,
            100.0,
            now()->toDateString(),
            '1200',
            'TEST-PAY-001'
        );

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(100.0, (float) $invoice->amount_paid);
        $this->assertSame(0.0, $this->invoices->remainingBalance($invoice));
    }

    public function test_void_reverses_posted_invoice(): void
    {
        $invoice = $this->createPostedInvoice(250.0);
        $this->invoices->void($invoice->fresh());

        $invoice->refresh();
        $this->assertSame('void', $invoice->status);
        $this->assertSame(0.0, (float) $invoice->amount_paid);
    }

    public function test_toyyibpay_invoice_callback_rejects_unverified_payment(): void
    {
        $invoice = $this->createPostedInvoice(500.0);
        tenancy()->end();

        $ref = 'inv-'.$invoice->id.'-'.$this->tenant->id;

        $stub = Mockery::mock(ToyyibpayService::class);
        $stub->shouldReceive('verifyPaidBill')
            ->once()
            ->with('fake-bill', $ref)
            ->andReturn(false);
        $this->app->instance(ToyyibpayService::class, $stub);

        $response = $this->post('/pay/toyyibpay/callback', [
            'billcode'  => 'fake-bill',
            'status_id' => 1,
            'order_id'  => $ref,
        ]);

        $response->assertStatus(403);

        tenancy()->initialize($this->tenant);
        $this->assertSame('unpaid', $invoice->fresh()->status);
        $this->assertSame(0.0, (float) $invoice->fresh()->amount_paid);
    }

    private function createDraftInvoice(float $unitPrice = 100.0): Invoice
    {
        return $this->invoices->create([
            'invoice_number'  => 'INV-MP-'.uniqid('', true),
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
    }

    private function createPostedInvoice(float $unitPrice = 100.0): Invoice
    {
        $invoice = $this->createDraftInvoice($unitPrice);
        $this->invoices->post($invoice);

        return $invoice->fresh(['items', 'customer']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
