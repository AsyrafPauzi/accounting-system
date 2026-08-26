<?php

namespace Tests\Feature\Ocr;

use App\Jobs\ProcessOcr;
use App\Models\Bill;
use App\Models\OcrJob;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\TaxCode;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OCRService;
use App\Support\TaxCodeDefaults;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptInboxTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);

        $this->tenant = $this->createTenantWithDatabase();
        $this->tenant->forceFill([
            'provision_status' => 'ready',
            'provisioned_at'   => now(),
        ])->save();

        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id'   => Plan::where('slug', 'solo')->firstOrFail()->id,
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
        TaxCodeDefaults::seedMissing();
        tenancy()->end();
    }

    public function test_upload_creates_pending_job_and_process_ocr_marks_ready(): void
    {
        Storage::fake('public');

        $this->mock(OCRService::class, function ($mock): void {
            $mock->shouldReceive('process')->once()->andReturn([
                'status' => 'success',
                'data'   => [
                    'vendor_name'   => 'Kedai Maju',
                    'bill_date'     => '2026-08-01',
                    'total_amount'  => 108.0,
                    'tax_amount'    => 8.0,
                    'items'         => [[
                        'description' => 'Supplies',
                        'amount'      => 100.0,
                        'quantity'    => 1,
                        'unit_amount' => 100.0,
                    ]],
                ],
            ]);
        });

        Queue::fake();

        $response = $this->actingAs($this->user)->post(route('receipts.store'), [
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        $response->assertRedirect();

        tenancy()->initialize($this->tenant);
        $job = OcrJob::query()->first();
        $this->assertNotNull($job);
        $this->assertSame('pending', $job->status);
        tenancy()->end();

        Queue::assertPushed(ProcessOcr::class, function (ProcessOcr $queued) use ($job) {
            return $queued->ocrJobId === $job->id;
        });

        tenancy()->initialize($this->tenant);
        (new ProcessOcr($job->file_path, null, $job->id))->handle(app(OCRService::class));
        $job->refresh();

        $this->assertSame('ready', $job->status);
        $this->assertSame('Kedai Maju', $job->parsed_data['vendor_name'] ?? null);
        tenancy()->end();
    }

    public function test_confirm_creates_bill_draft_with_tax_code(): void
    {
        tenancy()->initialize($this->tenant);
        Storage::fake('public');
        Storage::disk('public')->put('receipts/test.jpg', 'fake');

        $sr8 = TaxCode::query()->where('code', 'SR-8')->firstOrFail();

        $job = OcrJob::create([
            'file_path'         => 'receipts/test.jpg',
            'original_filename' => 'test.jpg',
            'status'            => 'ready',
            'parsed_data'       => [
                'vendor_name'  => 'OCR Supplier Sdn Bhd',
                'bill_date'    => '2026-08-01',
                'tax_amount'   => 8.0,
                'total_amount' => 108.0,
            ],
            'created_by'        => $this->user->id,
        ]);
        tenancy()->end();

        $response = $this->actingAs($this->user)->post(route('receipts.confirm', $job->id), [
            'vendor_name' => 'OCR Supplier Sdn Bhd',
            'bill_date'   => '2026-08-01',
            'tax_code_id' => $sr8->id,
            'tax_amount'  => 8,
            'items'       => [[
                'account_code' => '5000',
                'description'  => 'Supplies',
                'quantity'     => 1,
                'unit_amount'  => 100,
                'amount'       => 100,
                'tax_code_id'  => $sr8->id,
            ]],
        ]);

        $response->assertRedirect();

        tenancy()->initialize($this->tenant);
        $job->refresh();
        $this->assertSame('confirmed', $job->status);
        $this->assertNotNull($job->bill_id);

        $bill = Bill::with('items')->findOrFail($job->bill_id);
        $this->assertSame('draft', $bill->status);
        $this->assertSame('receipts/test.jpg', $bill->receipt_path);
        $this->assertSame(8.0, (float) $bill->tax_amount);
        $this->assertSame($sr8->id, (int) $bill->items->first()->tax_code_id);
        tenancy()->end();
    }

    public function test_retry_requeues_failed_job(): void
    {
        Queue::fake();

        tenancy()->initialize($this->tenant);
        $job = OcrJob::create([
            'file_path'     => 'receipts/failed.jpg',
            'status'        => 'failed',
            'error_message' => 'Provider timeout',
        ]);
        tenancy()->end();

        $response = $this->actingAs($this->user)->post(route('receipts.retry', $job->id));
        $response->assertRedirect();

        tenancy()->initialize($this->tenant);
        $job->refresh();
        $this->assertSame('pending', $job->status);
        $this->assertNull($job->error_message);
        tenancy()->end();

        Queue::assertPushed(ProcessOcr::class);
    }

    public function test_discard_marks_job_discarded(): void
    {
        tenancy()->initialize($this->tenant);
        $job = OcrJob::create([
            'file_path' => 'receipts/discard-me.jpg',
            'status'    => 'ready',
        ]);
        tenancy()->end();

        $response = $this->actingAs($this->user)->post(route('receipts.discard', $job->id));
        $response->assertRedirect(route('receipts.index'));

        tenancy()->initialize($this->tenant);
        $this->assertSame('discarded', $job->fresh()->status);
        tenancy()->end();
    }

    public function test_startup_plan_cannot_access_inbox(): void
    {
        Subscription::query()->where('tenant_id', $this->tenant->id)->delete();
        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id'   => Plan::where('slug', 'startup')->firstOrFail()->id,
            'status'    => 'active',
            'interval'  => 'lifetime',
            'gateway'   => 'system',
        ]);

        $this->actingAs($this->user)
            ->get(route('receipts.index'))
            ->assertRedirect(route('subscription.index'));
    }
}
