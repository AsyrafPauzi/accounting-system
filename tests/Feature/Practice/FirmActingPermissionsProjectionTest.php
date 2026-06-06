<?php

namespace Tests\Feature\Practice;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Firm;
use App\Models\FirmClient;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the contract that a firm-user acting on a client tenant sees
 * tenant-level permissions in the Inertia `auth.permissions` shared
 * prop. The sidebar / button gates read that array directly, so
 * without this projection menu items hide even though the route
 * handlers themselves accept the request (Gate::before grants the
 * abilities at the server layer).
 */
class FirmActingPermissionsProjectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeFirmOwnerLinkedTo(string $tenantId): User
    {
        $firm = Firm::create(['name' => 'Test Firm', 'slug' => 'firm-perms-'.uniqid('', true), 'status' => 'active']);
        $tenant = Tenant::find($tenantId)
            ?? Tenant::withoutEvents(fn () => Tenant::forceCreate(['id' => $tenantId]));

        $owner = User::factory()->create([
            'tenant_id' => null,
            'firm_id'   => $firm->id,
            'firm_role' => 'owner',
        ])->fresh();
        $owner->assignRole('firm-owner');

        FirmClient::create([
            'firm_id'           => $firm->id,
            'tenant_id'         => $tenant->id,
            'permission_level'  => 'admin',
            'status'            => 'active',
            'linked_at'         => now(),
            'linked_by_user_id' => $owner->id,
        ]);

        return $owner;
    }

    private function projectFor(User $user): array
    {
        $middleware = app(HandleInertiaRequests::class);
        // The method is `protected` but we explicitly want to test it
        // in isolation — Reflection is the lightest way to do that
        // without scaffolding a full request through the middleware.
        $r = new \ReflectionMethod($middleware, 'projectedPermissions');
        $r->setAccessible(true);
        return $r->invoke($middleware, $user);
    }

    public function test_firm_user_outside_client_only_sees_own_permissions(): void
    {
        $owner = $this->makeFirmOwnerLinkedTo('proj-out-1');

        $this->actingAs($owner);
        // No tenancy initialised — they're at the firm console level.

        $perms = $this->projectFor($owner);

        // Has the firm-level permissions...
        $this->assertContains('practice.access', $perms);
        $this->assertContains('practice.clients.view', $perms);
        // ...but NOT tenant-level ones.
        $this->assertNotContains('invoices.view', $perms);
        $this->assertNotContains('customers.view', $perms);
        $this->assertNotContains('bills.view', $perms);
    }

    public function test_firm_user_inside_client_sees_full_tenant_permission_set(): void
    {
        $tenant = Tenant::withoutEvents(fn () => Tenant::forceCreate(['id' => 'proj-in-1']));
        $owner = $this->makeFirmOwnerLinkedTo($tenant->id);

        $this->actingAs($owner);
        tenancy()->initialize($tenant);

        try {
            $perms = $this->projectFor($owner);

            // Tenant-level perms now appear so the sidebar renders.
            $this->assertContains('invoices.view', $perms);
            $this->assertContains('invoices.create', $perms);
            $this->assertContains('customers.view', $perms);
            $this->assertContains('customers.edit', $perms);
            $this->assertContains('bills.view', $perms);
            $this->assertContains('reports.view', $perms);
            $this->assertContains('accounts.view', $perms);

            // Firm-level perms are still there.
            $this->assertContains('practice.access', $perms);

            // Central super-admin perms are NOT projected — those are
            // platform-operator rights, never tenant-scoped.
            $this->assertNotContains('admin.tenants', $perms);
            $this->assertNotContains('admin.users', $perms);
        } finally {
            tenancy()->end();
        }
    }

    public function test_firm_user_without_practice_access_does_not_get_projection(): void
    {
        $tenant = Tenant::withoutEvents(fn () => Tenant::forceCreate(['id' => 'proj-noaccess-1']));
        $owner = $this->makeFirmOwnerLinkedTo($tenant->id);

        // Strip the role so practice.access is gone — simulates a
        // disabled / revoked staff member with a stale session.
        $owner->roles()->detach();
        $owner = $owner->fresh()->load('permissions', 'roles');

        $this->actingAs($owner);
        tenancy()->initialize($tenant);

        try {
            $perms = $this->projectFor($owner);

            $this->assertNotContains('invoices.view', $perms);
            $this->assertNotContains('customers.view', $perms);
        } finally {
            tenancy()->end();
        }
    }

    public function test_sme_admin_projection_is_unchanged(): void
    {
        // Regression — making sure the firm-user branch doesn't leak
        // into the regular SME admin path.
        $tenant = Tenant::withoutEvents(fn () => Tenant::forceCreate(['id' => 'proj-sme-1']));

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
        ])->fresh();
        $admin->assignRole('admin');

        $this->actingAs($admin);
        tenancy()->initialize($tenant);

        try {
            $perms = $this->projectFor($admin);

            // SME admin retains their stored set.
            $this->assertContains('invoices.view', $perms);
            $this->assertContains('customers.view', $perms);
            // And does NOT pick up the projected set spuriously
            // (they're not a firm user).
            // The `admin` role has most permissions anyway, so this is
            // really just a smoke check that nothing is missing.
            $this->assertGreaterThan(20, count($perms));
        } finally {
            tenancy()->end();
        }
    }
}
