<?php

namespace Tests\Feature\Onboarding;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\OnboardingChecklist;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_sees_checklist_with_company_step(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);

        $tenant = $this->createTenantWithDatabase();
        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id'   => Plan::where('slug', 'startup')->firstOrFail()->id,
            'status'    => 'active',
            'interval'  => 'lifetime',
            'gateway'   => 'system',
        ]);

        $adminRole = Role::where('name', 'admin')->first();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role_id' => $adminRole?->id]);
        if ($adminRole) {
            $user->assignRole('admin');
        }

        tenancy()->initialize($tenant);
        $checklist = OnboardingChecklist::forUser($user, $tenant);

        $this->assertTrue($checklist['visible']);
        $this->assertSame(4, $checklist['total']);
        $this->assertSame('company', $checklist['steps'][0]['key']);
    }

    public function test_dismiss_checklist_hides_widget(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);

        $tenant = $this->createTenantWithDatabase();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user)
            ->post(route('onboarding.checklist.dismiss'))
            ->assertRedirect();

        $user->refresh();
        $checklist = OnboardingChecklist::forUser($user, $tenant);
        $this->assertFalse($checklist['visible']);
    }
}
