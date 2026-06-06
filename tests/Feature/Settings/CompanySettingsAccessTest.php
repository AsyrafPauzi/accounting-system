<?php

namespace Tests\Feature\Settings;

use App\Models\Firm;
use App\Models\FirmClient;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the contract that a firm-user acting on a client tenant with
 * `admin` permission_level can edit that tenant's company settings —
 * the same way an SME admin would on their own tenant.
 *
 * The previous implementation gated the update on the central spatie
 * role only (`admin`/`super-admin`), which firm-users never have, so
 * accountants found themselves in an unintentional read-only mode.
 */
class CompanySettingsAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_user_canAdminCurrentTenant_returns_true_for_firm_owner_with_admin_pivot(): void
    {
        $firm = Firm::create(['name' => 'Test Firm', 'slug' => 'cs-firm', 'status' => 'active']);
        $clientTenant = Tenant::withoutEvents(fn () => Tenant::forceCreate(['id' => 'cs-client']));

        $owner = User::factory()->create([
            'tenant_id' => null,
            'firm_id'   => $firm->id,
            'firm_role' => 'owner',
        ])->fresh();
        $owner->assignRole('firm-owner');

        FirmClient::create([
            'firm_id'           => $firm->id,
            'tenant_id'         => $clientTenant->id,
            'permission_level'  => 'admin',
            'status'            => 'active',
            'linked_at'         => now(),
            'linked_by_user_id' => $owner->id,
        ]);

        $this->actingAs($owner);
        tenancy()->initialize($clientTenant);

        try {
            $this->assertTrue($owner->canAdminCurrentTenant());
        } finally {
            tenancy()->end();
        }
    }

    public function test_user_canAdminCurrentTenant_returns_false_for_firm_user_with_viewer_pivot(): void
    {
        $firm = Firm::create(['name' => 'Test Firm', 'slug' => 'cs-firm-viewer', 'status' => 'active']);
        $clientTenant = Tenant::withoutEvents(fn () => Tenant::forceCreate(['id' => 'cs-client-viewer']));

        $staff = User::factory()->create([
            'tenant_id' => null,
            'firm_id'   => $firm->id,
            'firm_role' => 'staff',
        ])->fresh();
        $staff->assignRole('firm-staff');

        FirmClient::create([
            'firm_id'           => $firm->id,
            'tenant_id'         => $clientTenant->id,
            'permission_level'  => 'viewer',
            'status'            => 'active',
            'linked_at'         => now(),
            'linked_by_user_id' => $staff->id,
        ]);

        $this->actingAs($staff);
        tenancy()->initialize($clientTenant);

        try {
            $this->assertFalse($staff->canAdminCurrentTenant());
        } finally {
            tenancy()->end();
        }
    }

    public function test_user_canAdminCurrentTenant_returns_false_when_pivot_missing(): void
    {
        // Firm-user with no FirmClient row for this tenant — must NOT
        // be granted admin authority just because they're a firm-user.
        $firm = Firm::create(['name' => 'Other Firm', 'slug' => 'cs-firm-other', 'status' => 'active']);
        $clientTenant = Tenant::withoutEvents(fn () => Tenant::forceCreate(['id' => 'cs-client-stranger']));

        $stranger = User::factory()->create([
            'tenant_id' => null,
            'firm_id'   => $firm->id,
            'firm_role' => 'owner',
        ])->fresh();
        $stranger->assignRole('firm-owner');

        $this->actingAs($stranger);
        tenancy()->initialize($clientTenant);

        try {
            $this->assertFalse($stranger->canAdminCurrentTenant());
        } finally {
            tenancy()->end();
        }
    }

    public function test_sme_admin_canAdminCurrentTenant_on_their_own_tenant(): void
    {
        $tenant = Tenant::withoutEvents(fn () => Tenant::forceCreate(['id' => 'cs-sme']));

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
        ])->fresh();
        $admin->assignRole('admin');

        $this->actingAs($admin);
        tenancy()->initialize($tenant);

        try {
            $this->assertTrue($admin->canAdminCurrentTenant());
        } finally {
            tenancy()->end();
        }
    }

    public function test_sme_accountant_role_does_not_grant_tenant_admin(): void
    {
        // The `accountant` role has full bookkeeping permissions but is
        // explicitly NOT a tenant admin — they should not edit
        // company-level settings.
        $tenant = Tenant::withoutEvents(fn () => Tenant::forceCreate(['id' => 'cs-sme-acct']));

        $accountant = User::factory()->create([
            'tenant_id' => $tenant->id,
        ])->fresh();
        $accountant->assignRole('accountant');

        $this->actingAs($accountant);
        tenancy()->initialize($tenant);

        try {
            $this->assertFalse($accountant->canAdminCurrentTenant());
        } finally {
            tenancy()->end();
        }
    }
}
