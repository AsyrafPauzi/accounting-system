<?php

namespace Tests\Feature\Bills;

use App\Jobs\ProcessOcr;
use App\Models\Bill;
use App\Models\BillDocumentVersion;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillDocumentService;
use App\Services\BillService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BillDocumentUploadTest extends TestCase
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
        $this->tenant->forceFill([
            'provision_status' => 'ready',
            'provisioned_at' => now(),
        ])->save();

        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => Plan::where('slug', 'solo')->firstOrFail()->id,
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
        $this->supplier = Supplier::create([
            'name' => 'Doc Supplier',
            'code' => 'SUP-DOC-001',
            'billing_country' => 'Malaysia',
            'is_active' => true,
        ]);
        $this->bills = app(BillService::class);
    }

    public function test_payment_receipt_upload_does_not_dispatch_ocr(): void
    {
        Queue::fake();
        Storage::fake('public');

        $bill = $this->createDraftBill();
        tenancy()->end();

        $file = UploadedFile::fake()->create('pay.pdf', 100, 'application/pdf');

        $this->actingAs($this->user)
            ->post(route('bills.upload-document'), [
                'slot' => 'payment_receipt',
                'document' => $file,
                'bill_id' => $bill->id,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'stored')
            ->assertJsonPath('apply_ocr', false);

        Queue::assertNotPushed(ProcessOcr::class);

        tenancy()->initialize($this->tenant);
        $this->assertNotNull($bill->fresh()->payment_receipt_path);
        $this->assertDatabaseHas('bill_document_versions', [
            'bill_id' => $bill->id,
            'slot' => 'payment_receipt',
            'action' => 'uploaded',
        ]);
        tenancy()->end();
    }

    public function test_supplier_invoice_upload_dispatches_ocr(): void
    {
        Queue::fake();
        Storage::fake('public');

        $bill = $this->createDraftBill();
        tenancy()->end();

        $file = UploadedFile::fake()->image('invoice.jpg');

        $this->actingAs($this->user)
            ->post(route('bills.upload-document'), [
                'slot' => 'supplier_invoice',
                'document' => $file,
                'bill_id' => $bill->id,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('apply_ocr', true);

        Queue::assertPushed(ProcessOcr::class);

        tenancy()->initialize($this->tenant);
        $this->assertNotNull($bill->fresh()->supplier_invoice_path);
        tenancy()->end();
    }

    public function test_posted_replace_requires_reason(): void
    {
        Storage::fake('public');

        $bill = $this->createPostedBill();
        tenancy()->end();

        $file = UploadedFile::fake()->create('pay2.pdf', 50, 'application/pdf');

        $this->actingAs($this->user)
            ->postJson(route('bills.upload-document'), [
                'slot' => 'payment_receipt',
                'document' => $file,
                'bill_id' => $bill->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_posted_replace_with_reason_keeps_prior_version(): void
    {
        Queue::fake();
        Storage::fake('public');

        $bill = $this->createPostedBill();
        $oldPath = 'receipts/old-invoice.pdf';
        Storage::disk('public')->put($oldPath, 'old');
        $bill->update(['supplier_invoice_path' => $oldPath]);
        BillDocumentVersion::create([
            'bill_id' => $bill->id,
            'slot' => 'supplier_invoice',
            'path' => $oldPath,
            'action' => 'uploaded',
        ]);
        $totalBefore = (float) $bill->total_amount;
        tenancy()->end();

        $file = UploadedFile::fake()->create('new-invoice.pdf', 80, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->postJson(route('bills.upload-document'), [
                'slot' => 'supplier_invoice',
                'document' => $file,
                'bill_id' => $bill->id,
                'reason' => 'Wrong scan from supplier',
            ]);

        $response->assertOk()
            ->assertJsonPath('apply_ocr', false);

        tenancy()->initialize($this->tenant);
        $bill->refresh();
        $this->assertNotSame($oldPath, $bill->supplier_invoice_path);
        $this->assertSame($totalBefore, (float) $bill->total_amount);
        $this->assertTrue(
            BillDocumentVersion::query()
                ->where('bill_id', $bill->id)
                ->where('slot', 'supplier_invoice')
                ->where('path', $oldPath)
                ->exists()
        );
        $this->assertTrue(
            BillDocumentVersion::query()
                ->where('bill_id', $bill->id)
                ->where('slot', 'supplier_invoice')
                ->where('action', 'replaced')
                ->where('reason', 'Wrong scan from supplier')
                ->exists()
        );

        $version = BillDocumentVersion::query()
            ->where('bill_id', $bill->id)
            ->where('path', $oldPath)
            ->firstOrFail();

        // Re-seed bytes for the historical path (tenancy disk bootstrap can
        // remount the fake root across HTTP requests).
        Storage::disk('public')->put($oldPath, 'old-bytes');
        tenancy()->end();

        $this->actingAs($this->user)
            ->get(route('bills.document-versions', [$bill->id, $version->id]))
            ->assertOk();
    }

    public function test_clear_after_post_is_rejected(): void
    {
        $bill = $this->createPostedBill();
        $bill->update(['payment_receipt_path' => 'receipts/keep.pdf']);

        $this->expectException(ValidationException::class);
        app(BillDocumentService::class)->clear($bill, 'payment_receipt', $this->user->id);
    }

    private function createDraftBill(): Bill
    {
        return $this->bills->create([
            'bill_number' => 'BILL-DOC-'.uniqid(),
            'supplier_id' => $this->supplier->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'created_by' => $this->user->id,
        ], [[
            'account_code' => '5000',
            'description' => 'Supplies',
            'amount' => 100,
        ]]);
    }

    private function createPostedBill(): Bill
    {
        $bill = $this->createDraftBill();
        $this->bills->post($bill);

        return $bill->fresh();
    }
}
