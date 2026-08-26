<?php

namespace Tests\Feature\Dashboard;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CreatesTestTenants;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CreatesTestTenants;
    use RefreshDatabase;

    public function test_provisioned_tenant_user_can_load_dashboard(): void
    {
        $tenant = $this->createTenantWithDatabase('dash-'.Str::lower(Str::random(8)));
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Dashboard'));
    }

    public function test_is_provisioned_treats_null_status_as_ready(): void
    {
        $tenant = new Tenant;
        $tenant->setRawAttributes([
            'id'               => 'legacy-tenant',
            'provision_status' => null,
        ]);

        $this->assertTrue($tenant->isProvisioned());
    }
}
