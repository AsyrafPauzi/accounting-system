<?php

namespace Tests\Feature\Purchases;

use App\Models\BillPayment;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillService;
use App\Support\DocumentNumberDefaults;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentVoucherPdfTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Supplier $supplier;

    private BillService $bills;

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

        $this->supplier = Supplier::create([
            'name'            => 'Voucher Supplier',
            'code'            => 'SUP-PV-001',
            'billing_country' => 'Malaysia',
            'is_active'       => true,
        ]);
        $this->bills = app(BillService::class);
    }

    public function test_payment_voucher_pdf_assigns_number_and_returns_pdf(): void
    {
        $bill = $this->createPostedBill(200.0);
        $payment = $this->bills->recordPayment(
            $bill,
            200.0,
            now()->toDateString(),
            '1200',
            'PV-REF-001'
        );

        $this->assertNull($payment->voucher_number);
        tenancy()->end();

        $response = $this->actingAs($this->user)->get(route('bills.payment-voucher', [
            $bill->id,
            $payment->id,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());

        tenancy()->initialize($this->tenant);
        $payment->refresh();
        $this->assertNotNull($payment->voucher_number);
        $this->assertMatchesRegularExpression('/^PV-\d+/', $payment->voucher_number);

        $second = $this->actingAs($this->user)->get(route('bills.payment-voucher', [
            $bill->id,
            $payment->id,
        ]));
        $second->assertOk();
        $this->assertSame($payment->fresh()->voucher_number, BillPayment::find($payment->id)->voucher_number);
    }

    private function createPostedBill(float $amount)
    {
        $bill = $this->bills->create([
            'bill_number' => 'BILL-PV-'.uniqid('', true),
            'supplier_id' => $this->supplier->id,
            'bill_date'   => now()->toDateString(),
            'due_date'    => now()->addDays(30)->toDateString(),
            'tax_amount'  => 0,
        ], [[
            'account_code' => '5000',
            'description'  => 'Office supplies',
            'amount'       => $amount,
        ]]);
        $this->bills->post($bill);

        return $bill->fresh(['items', 'supplier']);
    }
}
