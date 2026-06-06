<?php

namespace Tests\Feature\Practice;

use App\Mail\FirmInviteExistingClient;
use App\Models\Firm;
use App\Models\FirmInvitation;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Pins the contract for the firm-side "invite existing SME" flow:
 *
 *   1. Look up the email in the system first.
 *   2. If found → create a FirmInvitation row AND email the SME.
 *   3. If not found → return a clear "no account uses that email" error
 *      and do nothing destructive (no row created, no email sent).
 */
class InviteExistingClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeFirmOwnerOnSoloPlan(): User
    {
        // The Practice "Solo" plan has a non-zero client cap so the
        // guardCap() short-circuit doesn't bounce us before we get to
        // the email-existence check this test is really about.
        $plan = Plan::create([
            'name'             => 'Practice Solo',
            'slug'             => 'practice-solo',
            'price_monthly'    => 0,
            'price_yearly'     => 0,
            'users_included'   => 1,
            'extra_user_price' => 0,
            'features'         => [],
            'is_active'        => true,
            'client_cap'       => 5,
        ]);

        $firm = Firm::create([
            'name'   => 'Practice Firm',
            'slug'   => 'practice-firm-'.uniqid('', true),
            'status' => 'active',
        ]);

        // Practice firm subscription (firm_id set, tenant_id null).
        $subscription = Subscription::create([
            'firm_id'                => $firm->id,
            'tenant_id'              => null,
            'plan_id'                => $plan->id,
            'status'                 => 'active',
            'interval'               => 'monthly',
            'current_period_start'   => now()->toDateString(),
            'current_period_ends_at' => now()->addMonth()->toDateString(),
            'gateway'                => 'system',
        ]);
        $firm->forceFill(['firm_subscription_id' => $subscription->id])->save();

        $owner = User::factory()->create([
            'tenant_id' => null,
            'firm_id'   => $firm->id,
            'firm_role' => 'owner',
        ])->fresh();
        $owner->assignRole('firm-owner');
        $firm->forceFill(['owner_user_id' => $owner->id])->save();

        return $owner;
    }

    private function makeSmeUserOnTenant(string $tenantId, string $email): User
    {
        $tenant = Tenant::withoutEvents(fn () => Tenant::forceCreate(['id' => $tenantId]));

        return User::factory()->create([
            'email'     => $email,
            'tenant_id' => $tenant->id,
        ])->fresh();
    }

    public function test_invite_existing_with_unknown_email_returns_error_and_creates_nothing(): void
    {
        Mail::fake();

        $owner = $this->makeFirmOwnerOnSoloPlan();

        $this->actingAs($owner)
            ->post(route('practice.clients.invite'), ['email' => 'ghost@nowhere.test'])
            ->assertRedirect()
            ->assertSessionHasErrors(['email']);

        $this->assertDatabaseCount('firm_invitations', 0);
        Mail::assertNothingQueued();
    }

    public function test_invite_existing_with_known_sme_email_creates_invitation_and_queues_email(): void
    {
        Mail::fake();

        $owner = $this->makeFirmOwnerOnSoloPlan();
        $sme = $this->makeSmeUserOnTenant('invite-target-tenant', 'sme@example.test');

        $this->actingAs($owner)
            ->post(route('practice.clients.invite'), ['email' => 'sme@example.test'])
            ->assertRedirect(route('practice.dashboard'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('firm_invitations', [
            'firm_id'   => $owner->firm_id,
            'tenant_id' => $sme->tenant_id,
            'email'     => 'sme@example.test',
            'direction' => FirmInvitation::DIRECTION_FIRM_TO_CLIENT,
            'status'    => FirmInvitation::STATUS_PENDING,
        ]);

        Mail::assertQueued(FirmInviteExistingClient::class, function ($mail) {
            return $mail->hasTo('sme@example.test');
        });
    }

    public function test_invite_refuses_when_target_already_managed_by_a_firm(): void
    {
        Mail::fake();

        $owner = $this->makeFirmOwnerOnSoloPlan();
        $sme = $this->makeSmeUserOnTenant('already-managed', 'taken@example.test');

        // Pre-existing active link to a different firm.
        $otherFirm = Firm::create([
            'name'   => 'Other Firm',
            'slug'   => 'other-firm-'.uniqid('', true),
            'status' => 'active',
        ]);
        \App\Models\FirmClient::create([
            'firm_id'           => $otherFirm->id,
            'tenant_id'         => $sme->tenant_id,
            'permission_level'  => 'admin',
            'status'            => 'active',
            'linked_at'         => now(),
            'linked_by_user_id' => null,
        ]);

        $this->actingAs($owner)
            ->post(route('practice.clients.invite'), ['email' => 'taken@example.test'])
            ->assertRedirect()
            ->assertSessionHasErrors(['email']);

        $this->assertDatabaseCount('firm_invitations', 0);
        Mail::assertNothingQueued();
    }

    public function test_invite_refuses_duplicate_pending_invite_for_same_email(): void
    {
        Mail::fake();

        $owner = $this->makeFirmOwnerOnSoloPlan();
        $sme = $this->makeSmeUserOnTenant('dup-target', 'dup@example.test');

        FirmInvitation::create([
            'firm_id'          => $owner->firm_id,
            'tenant_id'        => $sme->tenant_id,
            'direction'        => FirmInvitation::DIRECTION_FIRM_TO_CLIENT,
            'email'            => 'dup@example.test',
            'token'            => FirmInvitation::generateToken(),
            'permission_level' => 'admin',
            'status'           => FirmInvitation::STATUS_PENDING,
            'expires_at'       => FirmInvitation::defaultExpiresAt(),
        ]);

        $this->actingAs($owner)
            ->post(route('practice.clients.invite'), ['email' => 'dup@example.test'])
            ->assertRedirect()
            ->assertSessionHasErrors(['email']);

        // Only the seed invitation should exist; we did not duplicate it
        // and we did not send another email.
        $this->assertDatabaseCount('firm_invitations', 1);
        Mail::assertNothingQueued();
    }
}
