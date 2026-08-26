<?php

namespace Tests\Feature\Practice;

use App\Models\Firm;
use App\Models\FirmClient;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirmViewerWriteBlockedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_firm_viewer_cannot_access_invoice_create_when_acting_on_client(): void
    {
        $firm = Firm::create(['name' => 'Viewer Firm', 'slug' => 'viewer-firm', 'status' => 'active']);
        $tenant = Tenant::withoutEvents(fn () => Tenant::forceCreate(['id' => 'viewer-client']));

        $staff = User::factory()->create([
            'tenant_id' => null,
            'firm_id'   => $firm->id,
            'firm_role' => 'staff',
        ])->fresh();
        $staff->assignRole('firm-staff');

        FirmClient::create([
            'firm_id'           => $firm->id,
            'tenant_id'         => $tenant->id,
            'permission_level'  => 'viewer',
            'status'            => 'active',
            'linked_at'         => now(),
            'linked_by_user_id' => $staff->id,
        ]);

        $this->actingAs($staff);
        session(['acting_tenant_id' => $tenant->id]);
        tenancy()->initialize($tenant);

        try {
            $this->get('/invoices/create')->assertForbidden();
        } finally {
            tenancy()->end();
        }
    }
}
