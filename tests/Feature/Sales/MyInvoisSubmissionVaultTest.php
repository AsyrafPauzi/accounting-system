<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MyInvoisSubmission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\MyInvoisService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MyInvoisSubmissionVaultTest extends TestCase
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
        $this->tenant->forceFill([
            'provision_status'       => 'ready',
            'tin'                    => 'C12345678901',
            'brn'                    => '202401012345',
            'legal_name'             => 'Vault Test Sdn Bhd',
            'display_name'           => 'Vault Test',
            'country'                => 'Malaysia',
            'msic_code'              => '62010',
            'street'                 => 'Lot 1, Jalan Test',
            'city'                   => 'Kuala Lumpur',
            'postcode'               => '50000',
            'state'                  => 'Wilayah Persekutuan',
            'phone'                  => '+60312345678',
            'email'                  => 'vault@test.my',
            'myinvois_client_id'     => 'sandbox-client',
            'myinvois_client_secret' => encrypt('sandbox-secret'),
        ])->save();

        $plan = Plan::where('slug', 'growth')->firstOrFail();
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
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $adminRole?->id,
        ]);
        if ($adminRole) {
            $this->user->assignRole('admin');
        }

        tenancy()->initialize($this->tenant);
        $this->customer = Customer::create([
            'name'              => 'Buyer Sdn Bhd',
            'code'              => 'CUST-VAULT-001',
            'email'             => 'buyer@test.my',
            'phone'             => '+60387654321',
            'tin'               => 'C20001234567',
            'brn'               => '201901012345',
            'identification_type' => 'BRN',
            'billing_street'    => '1 Jalan Ampang',
            'billing_city'      => 'Kuala Lumpur',
            'billing_zip'       => '50450',
            'billing_state'     => 'Wilayah Persekutuan',
            'billing_country'   => 'Malaysia',
            'is_active'         => true,
        ]);
        $this->invoices = app(InvoiceService::class);
    }

    public function test_submit_invoice_persists_submission_vault_row(): void
    {
        $invoice = $this->createPostedInvoice();

        app(MyInvoisService::class)->submit($invoice->fresh(['items', 'customer']));

        $this->assertDatabaseHas('myinvois_submissions', [
            'document_type' => 'invoice',
            'document_id'   => $invoice->id,
            'status'        => 'submitted',
        ]);

        $submission = MyInvoisSubmission::query()->firstOrFail();
        $this->assertIsArray($submission->request_json);
        $this->assertArrayHasKey('Invoice', $submission->request_json);
        $this->assertIsArray($submission->response_json);
        $this->assertNotNull($submission->lhdn_uuid);
    }

    public function test_failed_live_submit_still_persists_error_payload(): void
    {
        $invoice = $this->createPostedInvoice();

        Http::fake([
            '*connect/token*' => Http::response(['access_token' => 'fake-token'], 200),
            '*documentsubmissions*' => Http::response(['message' => 'Bad payload'], 422),
        ]);

        $this->app['env'] = 'local';

        try {
            app(MyInvoisService::class)->submit($invoice->fresh(['items', 'customer']));
            $this->fail('Expected LogicException on failed LHDN submit.');
        } catch (\LogicException) {
            // expected
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertDatabaseHas('myinvois_submissions', [
            'document_type' => 'invoice',
            'document_id'   => $invoice->id,
            'status'        => 'error',
            'http_status'   => 422,
        ]);

        $submission = MyInvoisSubmission::query()->firstOrFail();
        $this->assertIsArray($submission->request_json);
        $this->assertArrayHasKey('Invoice', $submission->request_json);
        $this->assertSame('Bad payload', $submission->response_json['message'] ?? null);
    }

    public function test_growth_user_can_open_submissions_audit_page(): void
    {
        $invoice = $this->createPostedInvoice();
        app(MyInvoisService::class)->submit($invoice->fresh(['items', 'customer']));
        tenancy()->end();

        $this->user->forceFill(['onboarding_steps' => []])->save();

        $response = $this->actingAs($this->user->fresh())->get(route('myinvois.submissions.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('MyInvois/Submissions')
            ->has('submissions.data', 1)
        );
    }

    private function createPostedInvoice(): Invoice
    {
        $invoice = $this->invoices->create([
            'invoice_number'  => 'INV-VAULT-'.uniqid('', true),
            'msic_code'       => '62010',
            'customer_id'     => $this->customer->id,
            'issue_date'      => now()->toDateString(),
            'due_date'        => now()->addDays(30)->toDateString(),
            'currency'        => 'MYR',
            'shipping_amount' => 0,
        ], [[
            'description'         => 'Consulting',
            'quantity'            => 1,
            'unit_price'          => 100,
            'tax_rate'            => 8,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);
        $this->invoices->post($invoice);

        return $invoice->fresh(['items', 'customer']);
    }
}
