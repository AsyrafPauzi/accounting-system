<?php

namespace Tests\Feature\Sales;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillMoneyPathTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

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

        tenancy()->initialize($this->tenant);
        $this->supplier = Supplier::create([
            'name'            => 'Bill Supplier',
            'code'            => 'SUP-BMP-001',
            'billing_country' => 'Malaysia',
            'is_active'       => true,
        ]);
        $this->bills = app(BillService::class);
    }

    public function test_post_bill_creates_posted_journal_and_unpaid_status(): void
    {
        $bill = $this->createDraftBill();
        $this->bills->post($bill);

        $bill->refresh();
        $this->assertSame('unpaid', $bill->status);
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => 'Bill',
            'reference_id'   => $bill->id,
            'status'         => 'posted',
        ]);
    }

    public function test_record_payment_marks_bill_paid(): void
    {
        $bill = $this->createPostedBill(120.0);

        $this->bills->recordPayment(
            $bill,
            120.0,
            now()->toDateString(),
            '1200',
            'TEST-BILL-PAY-001'
        );

        $bill->refresh();
        $this->assertSame('paid', $bill->status);
        $this->assertSame(120.0, (float) $bill->amount_paid);
        $this->assertSame(0.0, $this->bills->remainingBalance($bill));
    }

    public function test_void_reverses_posted_bill(): void
    {
        $bill = $this->createPostedBill(80.0);
        $this->bills->void($bill->fresh());

        $bill->refresh();
        $this->assertSame('void', $bill->status);
        $this->assertSame(0.0, (float) $bill->amount_paid);
    }

    private function createDraftBill(float $amount = 100.0)
    {
        return $this->bills->create([
            'bill_number' => 'BILL-MP-'.uniqid('', true),
            'supplier_id' => $this->supplier->id,
            'bill_date'   => now()->toDateString(),
            'due_date'    => now()->addDays(30)->toDateString(),
            'tax_amount'  => 0,
        ], [[
            'account_code' => '5000',
            'description'  => 'Office supplies',
            'amount'       => $amount,
        ]]);
    }

    private function createPostedBill(float $amount = 100.0)
    {
        $bill = $this->createDraftBill($amount);
        $this->bills->post($bill);

        return $bill->fresh(['items']);
    }
}
