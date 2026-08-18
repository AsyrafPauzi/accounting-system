<?php

namespace Tests\Feature\Onboarding;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeTourDismissTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_super_admin_can_dismiss_welcome_tour(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => null,
            'welcomed_at' => null,
        ]);
        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->from(route('admin.tenants.index'))
            ->post(route('onboarding.dismiss'))
            ->assertRedirect(route('admin.tenants.index'))
            ->assertSessionMissing('info');

        $this->assertNotNull($admin->fresh()->welcomed_at);
    }
}
