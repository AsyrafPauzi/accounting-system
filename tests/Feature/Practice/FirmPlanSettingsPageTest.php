<?php

namespace Tests\Feature\Practice;

use App\Models\Firm;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the contract that the firm-owner Plan & usage page (/settings/plan)
 * renders the rich Practice card layout with the data the front-end needs:
 * plan name, monthly/yearly/extra-seat prices, client cap usage, firm-staff
 * seat usage, and the marketing feature bullets.
 */
class FirmPlanSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    private function makeFirmOwnerOnPlan(string $planSlug): array
    {
        $firm = Firm::create([
            'name' => 'Plan Page Firm',
            'slug' => 'plan-page-firm-'.uniqid('', true),
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => null,
            'firm_id'   => $firm->id,
            'firm_role' => 'owner',
        ])->fresh();
        $owner->assignRole('firm-owner');

        $plan = Plan::where('slug', $planSlug)->firstOrFail();
        $sub = Subscription::create([
            'firm_id'                => $firm->id,
            'tenant_id'              => null,
            'plan_id'                => $plan->id,
            'status'                 => 'active',
            'interval'               => 'monthly',
            'gateway'                => 'system',
            'current_period_start'   => now()->toDateString(),
            'current_period_ends_at' => now()->addMonth()->toDateString(),
        ]);

        $firm->forceFill([
            'owner_user_id'         => $owner->id,
            'firm_subscription_id'  => $sub->id,
        ])->save();

        return [$owner, $firm, $sub, $plan];
    }

    public function test_firm_owner_sees_rich_plan_and_usage_payload(): void
    {
        [$owner] = $this->makeFirmOwnerOnPlan('practice-starter');

        $response = $this->actingAs($owner)->get('/settings/plan');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/PlanFirm')
            ->where('firm.name', 'Plan Page Firm')
            ->where('subscription.plan_name', 'Practice Starter')
            ->where('subscription.is_free', false)
            ->where('subscription.users_included', 1)
            ->where('subscription.extra_seats', 0)
            ->where('usage.client_count', 0)
            ->where('usage.client_cap', 5)
            ->where('usage.staff_count', 1)
            ->has('subscription.features')
            ->has('subscription.price_monthly')
            ->has('subscription.price_yearly')
            ->has('subscription.extra_user_price')
        );
    }

    public function test_unlimited_plan_reports_null_client_cap(): void
    {
        [$owner] = $this->makeFirmOwnerOnPlan('practice-firm');

        $response = $this->actingAs($owner)->get('/settings/plan');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/PlanFirm')
            ->where('subscription.plan_name', 'Practice Firm')
            ->where('usage.client_cap', null)
            ->where('subscription.users_included', 10)
        );
    }

    public function test_free_plan_marks_is_free(): void
    {
        [$owner, , $sub] = $this->makeFirmOwnerOnPlan('practice-free');

        $response = $this->actingAs($owner)->get('/settings/plan');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/PlanFirm')
            ->where('subscription.plan_name', 'Practice Free')
            ->where('subscription.is_free', true)
            ->where('usage.client_cap', 1)
        );
    }
}
