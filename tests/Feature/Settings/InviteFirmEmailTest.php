<?php

namespace Tests\Feature\Settings;

use App\Models\FirmInvitation;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InviteFirmEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeSmeAdmin(string $tenantId = 'invite-firm-email-tenant'): User
    {
        $tenant = Tenant::withoutEvents(fn () => Tenant::forceCreate([
            'id' => $tenantId,
            'provision_status' => 'ready',
        ]));

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ])->fresh();
        $user->assignRole('admin');

        return $user;
    }

    public function test_sme_inviting_accountant_queues_email_to_firm_address(): void
    {
        Mail::fake();

        $admin = $this->makeSmeAdmin();

        $this->actingAs($admin)
            ->post(route('settings.invite-firm.store'), [
                'email' => 'accountant@example.test',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        tenancy()->end();

        $this->assertDatabaseHas('firm_invitations', [
            'tenant_id' => $admin->tenant_id,
            'direction' => FirmInvitation::DIRECTION_CLIENT_TO_FIRM,
            'email' => 'accountant@example.test',
            'status' => FirmInvitation::STATUS_PENDING,
        ]);

        Mail::assertQueuedCount(1);
    }
}
