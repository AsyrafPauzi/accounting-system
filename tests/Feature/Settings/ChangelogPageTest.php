<?php

namespace Tests\Feature\Settings;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangelogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_changelog(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);

        $tenant = $this->createTenantWithDatabase();
        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id'   => Plan::where('slug', 'corporate')->firstOrFail()->id,
            'status'    => 'active',
            'interval'  => 'lifetime',
            'gateway'   => 'system',
        ]);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get(route('settings.changelog'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Changelog')
            ->has('releases', 10)
            ->where('releases.0.id', 'wave-4')
            ->where('meta.first_commit', '2026-03-10')
            ->where('releases.9.id', '2026-03-foundation'));
    }
}
