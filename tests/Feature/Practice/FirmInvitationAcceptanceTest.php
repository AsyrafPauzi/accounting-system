<?php

namespace Tests\Feature\Practice;

use App\Models\Firm;
use App\Models\FirmClient;
use App\Models\FirmInvitation;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the contract that the firm-invite link
 * (`/firm-invite/{token}`) is reachable by a firm-owner without
 * requiring email verification, and is not intercepted by the
 * `EnsureSubscribed` firm-user fallthrough that bounces unrecognised
 * routes to the practice dashboard.
 *
 * Regression: an SME generated an invite token, shared the URL with
 * their accountant, and the accountant — freshly registered, email
 * unverified — clicked the link only to be redirected back to the
 * practice dashboard ("homepage"). The page never rendered, so they
 * could never accept.
 *
 * Two layers were causing the bounce:
 *   1. `verified` middleware on the route group → `/verify-email`.
 *   2. `EnsureSubscribed::isAlwaysAllowed` did not include the
 *      `firm.invite.` route prefix, so the firm-user fallthrough
 *      sent them to `practice.dashboard` regardless.
 */
class FirmInvitationAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** Build a firm owner with a deliberately unverified email. */
    private function makeUnverifiedFirmOwner(): User
    {
        $firm = Firm::create([
            'name'   => 'Acme Bookkeepers',
            'slug'   => 'acme-bookkeepers-'.uniqid('', true),
            'status' => 'active',
        ]);

        $owner = User::factory()->unverified()->create([
            'tenant_id'         => null,
            'firm_id'           => $firm->id,
            'firm_role'         => 'owner',
            'email_verified_at' => null,
        ])->fresh();
        $owner->assignRole('firm-owner');

        $firm->forceFill(['owner_user_id' => $owner->id])->save();

        return $owner;
    }

    /** Build a tenant with a pending client→firm invitation. */
    private function makePendingInviteFor(string $tenantId): FirmInvitation
    {
        $tenant = Tenant::find($tenantId)
            ?? Tenant::withoutEvents(fn () => Tenant::forceCreate([
                'id'           => $tenantId,
                'display_name' => 'Solar SME Sdn Bhd',
            ]));

        return FirmInvitation::create([
            'firm_id'          => null,
            'tenant_id'        => $tenant->id,
            'direction'        => FirmInvitation::DIRECTION_CLIENT_TO_FIRM,
            'email'            => 'firm-owner@example.com',
            'token'            => FirmInvitation::generateToken(),
            'permission_level' => 'admin',
            'status'           => FirmInvitation::STATUS_PENDING,
            'expires_at'       => FirmInvitation::defaultExpiresAt(),
        ]);
    }

    public function test_unverified_firm_owner_can_load_accept_invite_page(): void
    {
        $owner = $this->makeUnverifiedFirmOwner();
        $invite = $this->makePendingInviteFor('tenant-accept-invite-1');

        $response = $this->actingAs($owner)->get('/firm-invite/'.$invite->token);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Practice/AcceptInvite')
            ->where('invitation.token', $invite->token)
            ->where('invitation.tenant_id', 'tenant-accept-invite-1')
        );
    }

    public function test_unverified_firm_owner_can_accept_and_link_client(): void
    {
        $owner = $this->makeUnverifiedFirmOwner();
        $invite = $this->makePendingInviteFor('tenant-accept-invite-2');

        $response = $this->actingAs($owner)->post('/firm-invite/'.$invite->token);

        $response->assertRedirect(route('practice.dashboard'));
        $this->assertDatabaseHas('firm_clients', [
            'firm_id'   => $owner->firm_id,
            'tenant_id' => 'tenant-accept-invite-2',
            'status'    => 'active',
        ]);
        $this->assertDatabaseHas('firm_invitations', [
            'id'                  => $invite->id,
            'firm_id'             => $owner->firm_id,
            'status'              => FirmInvitation::STATUS_ACCEPTED,
            'accepted_by_user_id' => $owner->id,
        ]);
    }

    public function test_firm_invite_route_is_not_bounced_by_ensure_subscribed(): void
    {
        // EnsureSubscribed used to send firm-users without an
        // `acting_tenant_id` straight to `practice.dashboard` for
        // any route name not in the always-allowed list. Here we
        // assert the GET resolves directly (200) — i.e. it isn't
        // being short-circuited to `/practice`.
        $owner = $this->makeUnverifiedFirmOwner();
        $invite = $this->makePendingInviteFor('tenant-accept-invite-3');

        $response = $this->actingAs($owner)->get('/firm-invite/'.$invite->token);

        $response->assertStatus(200);
        $response->assertHeaderMissing('Location');
    }

    public function test_non_firm_owner_cannot_accept_even_if_they_can_see_page(): void
    {
        // A regular SME user clicking the link by mistake would render
        // the AcceptInvite page (we don't pre-gate the GET so the
        // error message is meaningful) but the POST must reject.
        $smeUser = User::factory()->create([
            'tenant_id' => 'sme-tenant-x',
            'firm_id'   => null,
            'firm_role' => null,
        ])->fresh();

        $invite = $this->makePendingInviteFor('tenant-accept-invite-4');

        $response = $this->actingAs($smeUser)->post('/firm-invite/'.$invite->token);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('firm_clients', [
            'tenant_id' => 'tenant-accept-invite-4',
        ]);
    }

    public function test_accepting_does_not_double_link_an_already_managed_tenant(): void
    {
        $owner = $this->makeUnverifiedFirmOwner();
        $tenantId = 'tenant-accept-invite-5';
        $invite = $this->makePendingInviteFor($tenantId);

        // A different firm already manages this tenant.
        $otherFirm = Firm::create([
            'name'   => 'Other Firm',
            'slug'   => 'other-firm-'.uniqid('', true),
            'status' => 'active',
        ]);
        FirmClient::create([
            'firm_id'           => $otherFirm->id,
            'tenant_id'         => $tenantId,
            'permission_level'  => 'admin',
            'status'            => 'active',
            'linked_at'         => now(),
            'linked_by_user_id' => null,
        ]);

        $response = $this->actingAs($owner)->post('/firm-invite/'.$invite->token);

        $response->assertRedirect(route('practice.dashboard'));
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('firm_clients', [
            'firm_id'   => $owner->firm_id,
            'tenant_id' => $tenantId,
        ]);
    }
}
