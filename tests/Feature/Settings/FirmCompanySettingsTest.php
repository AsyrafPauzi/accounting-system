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
 * Pins the contract that /settings/company doesn't 404 for firm-users
 * sitting at the practice console. The controller now branches:
 *   - firm-user with no acting tenant → Firm settings page
 *   - firm-user inside a client       → tenant Company page
 *   - SME admin                       → tenant Company page (unchanged)
 */
class FirmCompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeFirmOwner(string $firmName = 'Asyraf & Co'): User
    {
        $firm = Firm::create([
            'name'          => $firmName,
            'slug'          => 'firm-co-'.uniqid('', true),
            'status'        => 'active',
            'contact_email' => 'hello@asyraf.co',
            'contact_phone' => '+60 12 000 0000',
            'country'       => 'Malaysia',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => null,
            'firm_id'   => $firm->id,
            'firm_role' => 'owner',
        ])->fresh();
        $owner->assignRole('firm-owner');

        $firm->forceFill(['owner_user_id' => $owner->id])->save();

        return $owner;
    }

    private function makeFirmStaff(): User
    {
        $firm = Firm::create([
            'name'   => 'Staff Firm',
            'slug'   => 'staff-firm-'.uniqid('', true),
            'status' => 'active',
        ]);

        $staff = User::factory()->create([
            'tenant_id' => null,
            'firm_id'   => $firm->id,
            'firm_role' => 'staff',
        ])->fresh();
        $staff->assignRole('firm-staff');

        return $staff;
    }

    public function test_firm_owner_at_console_sees_firm_settings_not_404(): void
    {
        $owner = $this->makeFirmOwner('Asyraf & Co');

        $response = $this->actingAs($owner)->get('/settings/company');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/CompanyFirm')
            ->where('firm.name', 'Asyraf & Co')
            ->where('firm.contact_email', 'hello@asyraf.co')
            ->where('canEdit', true)
        );
    }

    public function test_firm_staff_sees_firm_settings_read_only(): void
    {
        $staff = $this->makeFirmStaff();

        $response = $this->actingAs($staff)->get('/settings/company');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/CompanyFirm')
            ->where('canEdit', false)
        );
    }

    public function test_firm_owner_can_update_firm_settings(): void
    {
        $owner = $this->makeFirmOwner('Old Name Sdn Bhd');

        $response = $this->actingAs($owner)->patch('/settings/company', [
            'name'          => 'New Name Sdn Bhd',
            'contact_email' => 'updated@firm.com',
            'contact_phone' => '+60 12 999 9999',
            'country'       => 'Malaysia',
        ]);

        $response->assertRedirect('/settings/company');
        $response->assertSessionHas('success');

        $firm = Firm::where('owner_user_id', $owner->id)->first();
        $this->assertSame('New Name Sdn Bhd', $firm->name);
        $this->assertSame('updated@firm.com', $firm->contact_email);
    }

    public function test_firm_staff_cannot_update_firm_settings(): void
    {
        $staff = $this->makeFirmStaff();
        $originalName = $staff->firm()->first()->name;

        $response = $this->actingAs($staff)->patch('/settings/company', [
            'name' => 'Staff Tried To Rename',
        ]);

        $response->assertSessionHasNoErrors();
        // Staff get a friendly redirect with an error flash, not a write.
        $unchanged = $staff->firm()->first()->fresh();
        $this->assertSame($originalName, $unchanged->name);
    }

    public function test_firm_owner_acting_on_client_still_hits_tenant_form(): void
    {
        // Regression — make sure we didn't accidentally short-circuit
        // the tenant flow when the firm-owner is inside a client.
        $owner = $this->makeFirmOwner();

        $tenant = Tenant::withoutEvents(fn () => Tenant::forceCreate(['id' => 'firm-set-tenant-1']));
        FirmClient::create([
            'firm_id'           => $owner->firm_id,
            'tenant_id'         => $tenant->id,
            'permission_level'  => 'admin',
            'status'            => 'active',
            'linked_at'         => now(),
            'linked_by_user_id' => $owner->id,
        ]);

        $this->actingAs($owner);
        session(['acting_tenant_id' => $tenant->id]);
        tenancy()->initialize($tenant);

        try {
            $response = $this->get('/settings/company');
            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->component('Settings/Company')
            );
        } finally {
            tenancy()->end();
        }
    }
}
