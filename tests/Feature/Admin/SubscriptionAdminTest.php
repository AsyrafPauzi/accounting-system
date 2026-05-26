<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AdminSubscriptionService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $tenantUser;
    private Tenant $tenant;
    private Plan $plan;
    private Plan $smePlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Create super-admin user (no tenant)
        $this->superAdmin = User::factory()->create(['tenant_id' => null]);
        $this->superAdmin->assignRole('super-admin');

        // Create a plan without triggering Spatie permission sync
        $this->plan = Plan::create([
            'name'             => 'Startup (Free)',
            'slug'             => 'startup',
            'price_monthly'    => 0,
            'price_yearly'     => 0,
            'users_included'   => 1,
            'extra_user_price' => 0,
            'features'         => [],
            'is_active'        => true,
        ]);

        $this->smePlan = Plan::create([
            'name'             => 'SME',
            'slug'             => 'sme',
            'price_monthly'    => 79,
            'price_yearly'     => 853,
            'users_included'   => 1,
            'extra_user_price' => 0,
            'features'         => [],
            'is_active'        => true,
        ]);

        // Create a tenant record without triggering the DB-creation event
        $this->tenant = Tenant::withoutEvents(function () {
            return Tenant::forceCreate(['id' => 'test_tenant_001']);
        });

        // Create tenant user
        $this->tenantUser = User::factory()->create(['tenant_id' => $this->tenant->getKey()]);
        $this->tenantUser->assignRole('admin');

        // Give tenant an existing subscription
        Subscription::create([
            'tenant_id'              => $this->tenant->getKey(),
            'plan_id'                => $this->plan->id,
            'status'                 => 'active',
            'interval'               => 'monthly',
            'current_period_start'   => now()->toDateString(),
            'current_period_ends_at' => now()->addMonth()->toDateString(),
            'gateway'                => 'system',
        ]);
    }

    // --- Access control ---

    public function test_non_super_admin_cannot_access_tenant_admin(): void
    {
        $this->actingAs($this->tenantUser)
            ->get(route('admin.tenants.index'))
            ->assertStatus(403);
    }

    public function test_super_admin_can_access_tenant_admin(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.tenants.index'))
            ->assertStatus(200);
    }

    // --- Assign plan ---

    public function test_super_admin_can_assign_plan_with_monthly_duration(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('admin.tenants.subscription.assign', $this->tenant->getKey()), [
                'plan_id'  => $this->smePlan->id,
                'duration' => '1_month',
            ])
            ->assertRedirect(route('admin.tenants.index'));

        $sub = Subscription::where('tenant_id', $this->tenant->getKey())->first();
        $this->assertEquals($this->smePlan->id, $sub->plan_id);
        $this->assertEquals('active', $sub->status);
        $this->assertEquals('monthly', $sub->interval);
        $this->assertEquals('admin', $sub->gateway);
        $this->assertNotNull($sub->current_period_ends_at);
    }

    public function test_super_admin_can_assign_lifetime(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('admin.tenants.subscription.assign', $this->tenant->getKey()), [
                'plan_id'  => $this->smePlan->id,
                'duration' => 'lifetime',
            ])
            ->assertRedirect(route('admin.tenants.index'));

        $sub = Subscription::where('tenant_id', $this->tenant->getKey())->first();
        $this->assertEquals('lifetime', $sub->interval);
        $this->assertNull($sub->current_period_ends_at);
    }

    public function test_super_admin_can_assign_custom_end_date(): void
    {
        $customDate = now()->addMonths(3)->toDateString();

        $this->actingAs($this->superAdmin)
            ->put(route('admin.tenants.subscription.assign', $this->tenant->getKey()), [
                'plan_id'  => $this->smePlan->id,
                'duration' => 'custom',
                'ends_at'  => $customDate,
            ])
            ->assertRedirect(route('admin.tenants.index'));

        $sub = Subscription::where('tenant_id', $this->tenant->getKey())->first();
        $this->assertEquals($customDate, $sub->current_period_ends_at->toDateString());
    }

    public function test_assign_requires_active_plan(): void
    {
        $inactivePlan = Plan::create([
            'name' => 'Old Plan', 'slug' => 'old', 'price_monthly' => 0,
            'price_yearly' => 0, 'users_included' => 1, 'extra_user_price' => 0,
            'features' => [], 'is_active' => false,
        ]);

        $this->actingAs($this->superAdmin)
            ->put(route('admin.tenants.subscription.assign', $this->tenant->getKey()), [
                'plan_id'  => $inactivePlan->id,
                'duration' => '1_month',
            ])
            ->assertSessionHasErrors('plan_id');
    }

    public function test_custom_duration_requires_ends_at(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('admin.tenants.subscription.assign', $this->tenant->getKey()), [
                'plan_id'  => $this->smePlan->id,
                'duration' => 'custom',
                // ends_at omitted
            ])
            ->assertSessionHasErrors('ends_at');
    }

    // --- Extend ---

    public function test_super_admin_can_extend_subscription(): void
    {
        $sub = Subscription::where('tenant_id', $this->tenant->getKey())->first();
        $originalEnd = $sub->current_period_ends_at->toDateString();

        $this->actingAs($this->superAdmin)
            ->post(route('admin.tenants.subscription.extend', $this->tenant->getKey()), [
                'days' => 30,
            ])
            ->assertRedirect(route('admin.tenants.index'));

        $sub->refresh();
        $expected = Carbon::parse($originalEnd)->addDays(30)->toDateString();
        $this->assertEquals($expected, $sub->current_period_ends_at->toDateString());
        $this->assertEquals('active', $sub->status);
    }

    public function test_extend_validates_days(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.tenants.subscription.extend', $this->tenant->getKey()), [
                'days' => 0,
            ])
            ->assertSessionHasErrors('days');
    }

    // --- Cancel ---

    public function test_super_admin_can_cancel_subscription(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.tenants.subscription.cancel', $this->tenant->getKey()))
            ->assertRedirect(route('admin.tenants.index'));

        $sub = Subscription::where('tenant_id', $this->tenant->getKey())->first();
        $this->assertEquals('canceled', $sub->status);
    }

    // --- Lifetime ---

    public function test_super_admin_can_grant_lifetime(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.tenants.subscription.lifetime', $this->tenant->getKey()), [
                'plan_id' => $this->smePlan->id,
            ])
            ->assertRedirect(route('admin.tenants.index'));

        $sub = Subscription::where('tenant_id', $this->tenant->getKey())->first();
        $this->assertEquals('lifetime', $sub->interval);
        $this->assertNull($sub->current_period_ends_at);
        $this->assertEquals('active', $sub->status);
    }

    // --- Admin overrides tenant lock ---

    public function test_admin_can_change_plan_even_when_active_paid_subscription_exists(): void
    {
        // Give the tenant an active SME subscription (normally locked for tenant self-service)
        Subscription::where('tenant_id', $this->tenant->getKey())->update([
            'plan_id'  => $this->smePlan->id,
            'status'   => 'active',
            'interval' => 'yearly',
        ]);

        $this->actingAs($this->superAdmin)
            ->put(route('admin.tenants.subscription.assign', $this->tenant->getKey()), [
                'plan_id'  => $this->plan->id,
                'duration' => '1_month',
            ])
            ->assertRedirect(route('admin.tenants.index'));

        $sub = Subscription::where('tenant_id', $this->tenant->getKey())->first();
        $this->assertEquals($this->plan->id, $sub->plan_id);
    }

    // --- AdminSubscriptionService unit tests ---

    public function test_service_extend_from_today_when_subscription_expired(): void
    {
        Subscription::where('tenant_id', $this->tenant->getKey())->update([
            'status'                 => 'expired',
            'current_period_ends_at' => now()->subDays(10)->toDateString(),
        ]);

        $service = app(AdminSubscriptionService::class);
        $service->extend($this->tenant, 30);

        $sub = Subscription::where('tenant_id', $this->tenant->getKey())->first();
        $this->assertEquals('active', $sub->status);
        $expected = now()->addDays(30)->toDateString();
        $this->assertEquals($expected, $sub->current_period_ends_at->toDateString());
    }
}
