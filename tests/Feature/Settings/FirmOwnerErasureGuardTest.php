<?php

namespace Tests\Feature\Settings;

use App\Models\Firm;
use App\Models\FirmClient;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the contract that a firm-owner with active clients cannot
 * schedule account erasure. The guard makes sure unlinking is the
 * explicit path, so we don't end up with orphaned firms after the
 * 30-day cooling-off window expires.
 */
class FirmOwnerErasureGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeFirmOwnerWithClients(int $clientCount = 1): User
    {
        $firm = Firm::create([
            'name'   => 'Erasure Firm',
            'slug'   => 'erasure-firm-'.uniqid('', true),
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => null,
            'firm_id'   => $firm->id,
            'firm_role' => 'owner',
            'password'  => bcrypt('correct-password'),
        ]);
        $owner->assignRole('firm-owner');
        $firm->forceFill(['owner_user_id' => $owner->id])->save();

        for ($i = 0; $i < $clientCount; $i++) {
            $tenant = Tenant::withoutEvents(fn () => Tenant::forceCreate(['id' => 'erasure-client-'.uniqid('', true)]));
            FirmClient::create([
                'firm_id'           => $firm->id,
                'tenant_id'         => $tenant->id,
                'permission_level'  => 'admin',
                'status'            => 'active',
                'linked_at'         => now(),
                'linked_by_user_id' => $owner->id,
            ]);
        }

        // Refresh from DB so every column (including deletion_requested_at,
        // which the factory doesn't set) is in the model's attributes
        // array — Eloquent strict-mode is enabled in tests and would
        // otherwise throw when controller code reads those attributes.
        return $owner->fresh();
    }

    public function test_users_table_has_deletion_requested_at_column(): void
    {
        // Sanity check — the rest of the suite depends on this column.
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('users', 'deletion_requested_at'));
    }

    public function test_firm_owner_with_active_clients_cannot_request_erasure(): void
    {
        $owner = $this->makeFirmOwnerWithClients(2);

        $this->actingAs($owner)
            ->post(route('settings.account_erase.request'), [
                'password' => 'correct-password',
                'confirm'  => '1',
            ])
            ->assertRedirect(route('settings.account_erase.show'))
            ->assertSessionHas('error');

        $this->assertNull($owner->fresh()->deletion_requested_at);
    }

    public function test_firm_owner_with_no_clients_can_request_erasure(): void
    {
        $owner = $this->makeFirmOwnerWithClients(0);

        $this->actingAs($owner)
            ->post(route('settings.account_erase.request'), [
                'password' => 'correct-password',
                'confirm'  => '1',
            ])
            ->assertRedirect(route('settings.account_erase.show'))
            ->assertSessionHas('success');

        $this->assertNotNull($owner->fresh()->deletion_requested_at);
    }

    public function test_unlinking_active_clients_unblocks_erasure(): void
    {
        $owner = $this->makeFirmOwnerWithClients(1);

        // First attempt blocked.
        $this->actingAs($owner)
            ->post(route('settings.account_erase.request'), [
                'password' => 'correct-password',
                'confirm'  => '1',
            ])
            ->assertSessionHas('error');

        // Unlink the only client.
        FirmClient::where('firm_id', $owner->firm_id)->delete();

        // Second attempt succeeds.
        $this->actingAs($owner)
            ->post(route('settings.account_erase.request'), [
                'password' => 'correct-password',
                'confirm'  => '1',
            ])
            ->assertSessionHas('success');

        $this->assertNotNull($owner->fresh()->deletion_requested_at);
    }
}
