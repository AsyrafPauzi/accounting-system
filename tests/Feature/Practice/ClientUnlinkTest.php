<?php

namespace Tests\Feature\Practice;

use App\Models\Firm;
use App\Models\FirmClient;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the contract that an accountant (firm-owner) can break the
 * link between their firm and a client tenant — and that the tenant
 * row itself is left intact.
 */
class ClientUnlinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeFirmOwner(Firm $firm): User
    {
        $owner = User::factory()->create([
            'tenant_id' => null,
            'firm_id'   => $firm->id,
            'firm_role' => 'owner',
        ]);
        $owner->assignRole('firm-owner');

        $firm->forceFill(['owner_user_id' => $owner->id])->save();

        // fresh() so every column lands in the model — strict mode in
        // tests trips on factory-omitted attributes when controllers
        // read them (e.g. deletion_requested_at).
        return $owner->fresh();
    }

    private function makeFirmStaff(Firm $firm): User
    {
        $staff = User::factory()->create([
            'tenant_id' => null,
            'firm_id'   => $firm->id,
            'firm_role' => 'staff',
        ]);
        $staff->assignRole('firm-staff');

        return $staff->fresh();
    }

    private function makeLinkedClient(Firm $firm, string $tenantId = 'unlink-test-client'): array
    {
        $tenant = Tenant::withoutEvents(fn () => Tenant::forceCreate(['id' => $tenantId]));

        $link = FirmClient::create([
            'firm_id'           => $firm->id,
            'tenant_id'         => $tenant->id,
            'permission_level'  => 'admin',
            'status'            => 'active',
            'linked_at'         => now(),
            'linked_by_user_id' => $firm->owner_user_id,
        ]);

        return [$tenant, $link];
    }

    public function test_firm_owner_can_unlink_client(): void
    {
        $firm = Firm::create(['name' => 'Test Firm', 'slug' => 'test-firm-unlink', 'status' => 'active']);
        $owner = $this->makeFirmOwner($firm);
        [$tenant] = $this->makeLinkedClient($firm);

        $this->actingAs($owner)
            ->delete(route('practice.clients.unlink', $tenant->id))
            ->assertRedirect(route('practice.dashboard'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('firm_clients', [
            'firm_id'   => $firm->id,
            'tenant_id' => $tenant->id,
        ]);

        // Tenant row itself is preserved.
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
    }

    public function test_firm_staff_cannot_unlink_client(): void
    {
        $firm = Firm::create(['name' => 'Test Firm', 'slug' => 'test-firm-staff', 'status' => 'active']);
        $this->makeFirmOwner($firm);
        $staff = $this->makeFirmStaff($firm);
        [$tenant] = $this->makeLinkedClient($firm);

        $this->actingAs($staff)
            ->delete(route('practice.clients.unlink', $tenant->id))
            ->assertForbidden();

        $this->assertDatabaseHas('firm_clients', [
            'firm_id'   => $firm->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_firm_owner_cannot_unlink_other_firms_client(): void
    {
        $myFirm = Firm::create(['name' => 'My Firm', 'slug' => 'my-firm', 'status' => 'active']);
        $otherFirm = Firm::create(['name' => 'Other Firm', 'slug' => 'other-firm', 'status' => 'active']);

        $myOwner = $this->makeFirmOwner($myFirm);
        $this->makeFirmOwner($otherFirm);
        [$theirTenant] = $this->makeLinkedClient($otherFirm, 'other-firm-client');

        $this->actingAs($myOwner)
            ->delete(route('practice.clients.unlink', $theirTenant->id))
            ->assertNotFound();

        $this->assertDatabaseHas('firm_clients', [
            'firm_id'   => $otherFirm->id,
            'tenant_id' => $theirTenant->id,
        ]);
    }

    public function test_unlink_returns_404_when_no_link_exists(): void
    {
        $firm = Firm::create(['name' => 'Test Firm', 'slug' => 'test-firm-404', 'status' => 'active']);
        $owner = $this->makeFirmOwner($firm);

        $this->actingAs($owner)
            ->delete(route('practice.clients.unlink', 'never-linked-tenant'))
            ->assertNotFound();
    }
}
