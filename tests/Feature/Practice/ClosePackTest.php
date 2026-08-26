<?php

namespace Tests\Feature\Practice;

use App\Models\Customer;
use App\Models\Firm;
use App\Models\FirmClient;
use App\Models\Tenant;
use App\Services\InvoiceService;
use App\Services\Practice\PracticeMetricsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClosePackTest extends TestCase
{
    use RefreshDatabase;

    private PracticeMetricsService $metrics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->metrics = new PracticeMetricsService();
    }

    public function test_close_pack_shows_unbilled_count_for_draft_invoice(): void
    {
        $tenant = $this->createTenantWithDatabase('close-pack-client');

        tenancy()->initialize($tenant);
        $customer = Customer::create([
            'name'            => 'Close Pack Customer',
            'code'            => 'CPC-001',
            'email'           => 'close-pack@test.com',
            'billing_country' => 'Malaysia',
            'is_active'       => true,
        ]);

        app(InvoiceService::class)->create([
            'invoice_number'  => 'INV-CP-001',
            'msic_code'       => '70200',
            'customer_id'     => $customer->id,
            'issue_date'      => now()->toDateString(),
            'due_date'        => now()->addDays(30)->toDateString(),
            'currency'        => 'MYR',
            'shipping_amount' => 0,
        ], [[
            'description'         => 'Draft work',
            'quantity'            => 1,
            'unit_price'          => 500,
            'tax_rate'            => 0,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);
        tenancy()->end();

        $pack = $this->metrics->closePackForClient($tenant);

        $this->assertSame(1, $pack['unbilled']['count']);
        $this->assertSame(1, $pack['unbilled']['draft_count']);
        $this->assertSame(0, $pack['unbilled']['unsent_count']);
        $this->assertSame('watch', $pack['unbilled']['status']);
    }

    public function test_close_pack_counts_unsent_posted_invoice(): void
    {
        $tenant = $this->createTenantWithDatabase('close-pack-unsent');

        tenancy()->initialize($tenant);
        $customer = Customer::create([
            'name'            => 'Unsent Customer',
            'code'            => 'CPC-002',
            'email'           => 'unsent@test.com',
            'billing_country' => 'Malaysia',
            'is_active'       => true,
        ]);

        $invoice = app(InvoiceService::class)->create([
            'invoice_number'  => 'INV-CP-002',
            'msic_code'       => '70200',
            'customer_id'     => $customer->id,
            'issue_date'      => now()->toDateString(),
            'due_date'        => now()->addDays(30)->toDateString(),
            'currency'        => 'MYR',
            'shipping_amount' => 0,
        ], [[
            'description'         => 'Posted work',
            'quantity'            => 1,
            'unit_price'          => 200,
            'tax_rate'            => 0,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);
        app(InvoiceService::class)->post($invoice);
        tenancy()->end();

        $pack = $this->metrics->closePackForClient($tenant);

        $this->assertSame(1, $pack['unbilled']['count']);
        $this->assertSame(0, $pack['unbilled']['draft_count']);
        $this->assertSame(1, $pack['unbilled']['unsent_count']);
    }

    public function test_close_pack_flags_myinvois_gap_on_posted_invoice(): void
    {
        $tenant = $this->createTenantWithDatabase('close-pack-sst');

        tenancy()->initialize($tenant);
        $customer = Customer::create([
            'name'            => 'SST Gap Customer',
            'code'            => 'CPC-003',
            'email'           => 'sst-gap@test.com',
            'billing_country' => 'Malaysia',
            'is_active'       => true,
        ]);

        $invoice = app(InvoiceService::class)->create([
            'invoice_number'  => 'INV-CP-003',
            'msic_code'       => '70200',
            'customer_id'     => $customer->id,
            'issue_date'      => now()->toDateString(),
            'due_date'        => now()->addDays(30)->toDateString(),
            'currency'        => 'MYR',
            'shipping_amount' => 0,
        ], [[
            'description'         => 'Taxable sale',
            'quantity'            => 1,
            'unit_price'          => 100,
            'tax_rate'            => 0,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);
        app(InvoiceService::class)->post($invoice);
        tenancy()->end();

        $pack = $this->metrics->closePackForClient($tenant);

        $this->assertSame(1, $pack['sst_gaps']['count']);
        $this->assertSame('watch', $pack['sst_gaps']['status']);
    }

    public function test_practice_dashboard_includes_close_pack_per_client(): void
    {
        $firm = Firm::create(['name' => 'Close Pack Firm', 'slug' => 'close-pack-firm', 'status' => 'active']);
        $owner = UserHelper::makeFirmOwner($firm);
        $tenant = $this->createTenantWithDatabase('close-pack-dash');

        FirmClient::create([
            'firm_id'           => $firm->id,
            'tenant_id'         => $tenant->id,
            'permission_level'  => 'admin',
            'status'            => 'active',
            'linked_at'         => now(),
            'linked_by_user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('practice.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Practice/Dashboard')
                ->has('clients', 1)
                ->where('clients.0.tenant_id', $tenant->id)
                ->has('clients.0.close_pack')
                ->has('clients.0.close_pack.unbilled')
                ->has('clients.0.close_pack.overdue_ar')
                ->has('clients.0.close_pack.sst_gaps')
                ->has('clients.0.close_pack.period')
                ->has('clients.0.close_pack.payroll_remittance')
            );
    }
}

/**
 * @internal
 */
final class UserHelper
{
    public static function makeFirmOwner(Firm $firm): \App\Models\User
    {
        $owner = \App\Models\User::factory()->create([
            'tenant_id' => null,
            'firm_id'   => $firm->id,
            'firm_role' => 'owner',
        ]);
        $owner->assignRole('firm-owner');
        $firm->forceFill(['owner_user_id' => $owner->id])->save();

        return $owner->fresh();
    }
}
