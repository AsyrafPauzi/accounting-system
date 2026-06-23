<?php

namespace Tests\Feature\Settings;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TeamMemberEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeAdminWithTeamPlan(): User
    {
        $tenant = Tenant::withoutEvents(fn () => Tenant::forceCreate(['id' => 'team-mail-tenant']));

        $plan = Plan::create([
            'name' => 'Team Plan',
            'slug' => 'team-plan',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'users_included' => 3,
            'extra_user_price' => 0,
            'features' => [],
            'is_active' => true,
        ]);
        $plan->syncPermissions(Permission::whereIn('name', ['users.view', 'users.create'])->get());

        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'interval' => 'monthly',
            'current_period_start' => now()->toDateString(),
            'current_period_ends_at' => now()->addMonth()->toDateString(),
            'gateway' => 'system',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ])->fresh();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_adding_team_member_queues_welcome_email(): void
    {
        Mail::fake();
        Event::fake();

        $admin = $this->makeAdminWithTeamPlan();

        $this->actingAs($admin)
            ->post(route('settings.team.store'), [
                'name' => 'New Accountant',
                'email' => 'new-accountant@example.test',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => 'accountant',
            ])
            ->assertRedirect(route('settings.team.index'))
            ->assertSessionHas('success');

        Mail::assertQueuedCount(1);
    }
}
