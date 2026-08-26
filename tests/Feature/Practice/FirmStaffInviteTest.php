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

class FirmStaffInviteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    private function makeFirmOwnerOnPlan(string $planSlug = 'practice-starter'): array
    {
        $firm = Firm::create([
            'name'   => 'Staff Invite Firm',
            'slug'   => 'staff-invite-'.uniqid('', true),
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
            'owner_user_id'        => $owner->id,
            'firm_subscription_id' => $sub->id,
        ])->save();

        return [$owner, $firm, $sub, $plan];
    }

    private function makeFirmStaff(Firm $firm): User
    {
        $staff = User::factory()->create([
            'tenant_id' => null,
            'firm_id'   => $firm->id,
            'firm_role' => 'staff',
        ])->fresh();
        $staff->assignRole('firm-staff');

        return $staff;
    }

    public function test_firm_owner_can_invite_staff_member(): void
    {
        [$owner, $firm] = $this->makeFirmOwnerOnPlan('practice-growth');

        $this->actingAs($owner)
            ->post(route('practice.team.store'), [
                'name'                  => 'Junior Accountant',
                'email'                 => 'junior@firm.test',
                'password'              => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertRedirect(route('practice.team.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email'     => 'junior@firm.test',
            'firm_id'   => $firm->id,
            'firm_role' => 'staff',
        ]);
    }

    public function test_firm_staff_cannot_manage_team(): void
    {
        [$owner, $firm] = $this->makeFirmOwnerOnPlan('practice-growth');
        $staff = $this->makeFirmStaff($firm);

        $this->actingAs($staff)
            ->get(route('practice.team.index'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('practice.team.store'), [
                'name'                  => 'Blocked Invite',
                'email'                 => 'blocked@firm.test',
                'password'              => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertForbidden();
    }

    public function test_firm_owner_can_remove_staff_member(): void
    {
        [$owner, $firm] = $this->makeFirmOwnerOnPlan('practice-growth');
        $staff = $this->makeFirmStaff($firm);

        $this->actingAs($owner)
            ->delete(route('practice.team.destroy', $staff->id))
            ->assertRedirect(route('practice.team.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
    }

    public function test_staff_seat_cap_blocks_invite_on_single_seat_plan(): void
    {
        [$owner, $firm] = $this->makeFirmOwnerOnPlan('practice-starter');

        $this->assertSame(1, $firm->fresh()->staffSeatCap());
        $this->assertFalse($firm->fresh()->canAddStaff());

        $this->actingAs($owner)
            ->post(route('practice.team.store'), [
                'name'                  => 'Extra Staff',
                'email'                 => 'extra@firm.test',
                'password'              => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('users', ['email' => 'extra@firm.test']);
    }

    public function test_team_page_shows_seat_usage(): void
    {
        [$owner, $firm] = $this->makeFirmOwnerOnPlan('practice-growth');

        $this->withoutVite();

        $this->actingAs($owner)
            ->get(route('practice.team.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Practice/Team')
                ->where('seatStatus.total_seats', 3)
                ->where('seatStatus.used', 1)
                ->where('seatStatus.can_add', true)
            );
    }
}
