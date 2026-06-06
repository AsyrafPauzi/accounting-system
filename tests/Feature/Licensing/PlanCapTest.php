<?php

namespace Tests\Feature\Licensing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PlanCap;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the contract that PlanCap correctly identifies which tenant's
 * plan is in force right now. The cap-hit checks (customerCapHit /
 * bankAccountCapHit) trivially follow once the slug is resolved.
 *
 * The actual count check is exercised against the tenant DB in the
 * controller-level integration tests (CustomerController + COA),
 * which require a full tenancy bootstrap and live in a separate
 * harness.
 */
class PlanCapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeTenantOnPlan(string $planSlug): Tenant
    {
        $plan = Plan::create([
            'name'             => ucfirst($planSlug),
            'slug'             => $planSlug,
            'price_monthly'    => 0,
            'price_yearly'     => 0,
            'users_included'   => 1,
            'extra_user_price' => 0,
            'features'         => [],
            'is_active'        => true,
        ]);

        $tenant = Tenant::withoutEvents(fn () => Tenant::forceCreate(['id' => 'cap-test-'.$planSlug]));

        Subscription::create([
            'tenant_id'              => $tenant->getKey(),
            'plan_id'                => $plan->id,
            'status'                 => 'active',
            'interval'               => 'monthly',
            'current_period_start'   => now()->toDateString(),
            'current_period_ends_at' => now()->addMonth()->toDateString(),
            'gateway'                => 'system',
        ]);

        return $tenant;
    }

    public function test_returns_null_for_unauthenticated_user(): void
    {
        $this->assertNull(PlanCap::currentPlanSlug());
    }

    public function test_returns_null_for_user_without_tenant(): void
    {
        $u = User::factory()->create(['tenant_id' => null]);
        $this->actingAs($u);

        $this->assertNull(PlanCap::currentPlanSlug());
    }

    public function test_returns_startup_for_startup_tenant(): void
    {
        $tenant = $this->makeTenantOnPlan('startup');
        $u = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $this->actingAs($u);

        $this->assertSame('startup', PlanCap::currentPlanSlug());
    }

    public function test_returns_solo_for_solo_tenant(): void
    {
        $tenant = $this->makeTenantOnPlan('solo');
        $u = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $this->actingAs($u);

        $this->assertSame('solo', PlanCap::currentPlanSlug());
    }

    public function test_returns_null_in_self_hosted_mode(): void
    {
        $tenant = $this->makeTenantOnPlan('startup');
        $u = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $this->actingAs($u);

        config(['deployment.mode' => 'self_hosted']);

        // License-driven entitlements take over, plan caps don't apply.
        $this->assertNull(PlanCap::currentPlanSlug());
    }

    public function test_firm_user_resolves_to_acting_client_tenants_plan(): void
    {
        // Firm-owner acting on a Startup-plan client tenant should
        // see "startup" — they're working inside that client's books.
        $firm = \App\Models\Firm::create(['name' => 'Test Firm', 'slug' => 'test-firm', 'status' => 'active']);
        $clientTenant = $this->makeTenantOnPlan('startup');
        $firmOwner = User::factory()->create([
            'tenant_id' => null,
            'firm_id'   => $firm->id,
            'firm_role' => 'owner',
        ]);
        $this->actingAs($firmOwner);
        session(['acting_tenant_id' => $clientTenant->getKey()]);

        $this->assertSame('startup', PlanCap::currentPlanSlug());
    }

    public function test_firm_user_without_acting_tenant_returns_null(): void
    {
        $firm = \App\Models\Firm::create(['name' => 'Test Firm', 'slug' => 'test-firm-2', 'status' => 'active']);
        $firmOwner = User::factory()->create([
            'tenant_id' => null,
            'firm_id'   => $firm->id,
            'firm_role' => 'owner',
        ]);
        $this->actingAs($firmOwner);

        $this->assertNull(PlanCap::currentPlanSlug());
    }

    public function test_customer_cap_hit_is_false_when_not_on_startup(): void
    {
        // No-op short-circuit: any plan that isn't "startup" is exempt
        // from the cap, regardless of how many customers exist.
        $tenant = $this->makeTenantOnPlan('solo');
        $u = User::factory()->create(['tenant_id' => $tenant->getKey()]);
        $this->actingAs($u);

        $this->assertFalse(PlanCap::customerCapHit());
        $this->assertFalse(PlanCap::bankAccountCapHit());
    }
}
