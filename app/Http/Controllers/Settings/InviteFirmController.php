<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Firm;
use App\Models\FirmClient;
use App\Models\FirmInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tenant-side flow: an SME admin invites their existing accountancy
 * firm to take over their books. The firm receives the invite (by
 * email containing the signed acceptance URL) and accepts from inside
 * the Practice console.
 *
 * Mirror of the firm-initiated invite (in PracticeClientController,
 * built later) but inverted — the tenant is the inviter here.
 *
 * Security notes:
 *   - We do NOT auto-link by email match. The recipient firm has to
 *     authenticate as a firm-owner and click the signed acceptance
 *     URL. This stops typo-squatting and rogue firms grabbing tenants.
 *   - We refuse a second invite if there's already an active link
 *     (one tenant ↔ one firm).
 */
class InviteFirmController extends Controller
{
    public function show(Request $request): Response
    {
        $tenantId = tenant('id');
        abort_unless($tenantId, 404);

        // The firm-link row + outstanding invites both live on the
        // central DB; we have to query them from the central
        // connection because tenancy is currently initialised on the
        // tenant DB.
        $existingLink = FirmClient::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->with('firm')
            ->first();

        $pending = FirmInvitation::query()
            ->where('tenant_id', $tenantId)
            ->where('direction', FirmInvitation::DIRECTION_CLIENT_TO_FIRM)
            ->where('status', FirmInvitation::STATUS_PENDING)
            ->orderByDesc('id')
            ->get(['id', 'email', 'expires_at', 'created_at']);

        // Pending invitations the firm has sent us (the inverse direction).
        // Match by tenant_id when the firm targeted us by email and we
        // were already in the system.
        $incoming = FirmInvitation::query()
            ->where('tenant_id', $tenantId)
            ->where('direction', FirmInvitation::DIRECTION_FIRM_TO_CLIENT)
            ->where('status', FirmInvitation::STATUS_PENDING)
            ->with('firm')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Settings/InviteFirm', [
            'currentFirm' => $existingLink && $existingLink->firm ? [
                'name'             => $existingLink->firm->name,
                'permission_level' => $existingLink->permission_level,
                'linked_at'        => optional($existingLink->linked_at)->toIso8601String(),
            ] : null,
            'pending' => $pending->map(fn ($p) => [
                'id'         => $p->id,
                'email'      => $p->email,
                'expires_at' => $p->expires_at?->toIso8601String(),
                'created_at' => $p->created_at?->toIso8601String(),
            ]),
            'incoming' => $incoming->map(fn ($p) => [
                'id'         => $p->id,
                'firm_name'  => $p->firm?->name,
                'email'      => $p->email,
                'permission_level' => $p->permission_level,
                'expires_at' => $p->expires_at?->toIso8601String(),
                'created_at' => $p->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = tenant('id');
        abort_unless($tenantId, 404);

        // Platform-level disable: super-admin may have switched the
        // accountant feature off for this tenant. We refuse new
        // invites in that state without clobbering anything that
        // already exists.
        $tenant = \App\Models\Tenant::where('id', $tenantId)->first();
        if ($tenant && (bool) ($tenant->practice_disabled ?? false)) {
            return back()->withErrors(['email' => 'The accountant feature is currently disabled for your account. Please contact BukuCloud support.']);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        // Refuse if already linked.
        $existingLink = FirmClient::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->exists();
        if ($existingLink) {
            return back()->withErrors(['email' => 'This tenant is already linked to a firm. Unlink first to switch firms.']);
        }

        $invitation = FirmInvitation::create([
            'firm_id'          => null, // unknown until firm owner accepts
            'tenant_id'        => $tenantId,
            'direction'        => FirmInvitation::DIRECTION_CLIENT_TO_FIRM,
            'email'            => mb_strtolower($validated['email']),
            'token'            => FirmInvitation::generateToken(),
            'permission_level' => 'admin', // tenants invite firms with full access by default
            'status'           => FirmInvitation::STATUS_PENDING,
            'expires_at'       => FirmInvitation::defaultExpiresAt(),
        ]);

        // Build the acceptance URL. We don't sign at the route level
        // because the token *is* the secret — signing on top would
        // double the parameters without adding security.
        $acceptUrl = URL::route('firm.invite.accept', ['token' => $invitation->token]);

        // Real implementation will queue a Mailable here. For v1 we
        // log + flash the URL into the session so the firm can paste
        // it directly during demo / smoke testing.
        Log::info('Tenant invited firm', [
            'tenant_id'  => $tenantId,
            'email'      => $invitation->email,
            'invite_url' => $acceptUrl,
        ]);

        return back()->with('success', 'Invite created. Send this link to your accountant: '.$acceptUrl);
    }

    public function destroy(Request $request, int $invitationId): RedirectResponse
    {
        $tenantId = tenant('id');
        abort_unless($tenantId, 404);

        $invitation = FirmInvitation::query()
            ->where('id', $invitationId)
            ->where('tenant_id', $tenantId)
            ->where('direction', FirmInvitation::DIRECTION_CLIENT_TO_FIRM)
            ->where('status', FirmInvitation::STATUS_PENDING)
            ->first();

        abort_unless($invitation, 404);

        $invitation->update(['status' => FirmInvitation::STATUS_REVOKED]);

        return back()->with('success', 'Invite revoked.');
    }

    /**
     * Accept a firm-initiated invite. The firm asked to manage this
     * tenant's books — clicking accept links them. We refuse if a
     * different firm is already managing the tenant; the SME has to
     * unlink first.
     */
    public function acceptIncoming(Request $request, int $invitationId): RedirectResponse
    {
        $tenantId = tenant('id');
        abort_unless($tenantId, 404);

        $invitation = FirmInvitation::query()
            ->where('id', $invitationId)
            ->where('tenant_id', $tenantId)
            ->where('direction', FirmInvitation::DIRECTION_FIRM_TO_CLIENT)
            ->where('status', FirmInvitation::STATUS_PENDING)
            ->first();
        abort_unless($invitation, 404);

        if (! $invitation->isUsable()) {
            $invitation->update(['status' => FirmInvitation::STATUS_EXPIRED]);
            return back()->with('error', 'That invitation has expired.');
        }

        // One firm at a time. If a link already exists for this tenant,
        // refuse — even revoked invites past acceptance shouldn't
        // silently overwrite the active relationship.
        $existing = FirmClient::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->exists();
        if ($existing) {
            return back()->with('error', 'You are already linked to a firm. Unlink first to switch firms.');
        }

        DB::transaction(function () use ($invitation, $request, $tenantId) {
            FirmClient::create([
                'firm_id'           => $invitation->firm_id,
                'tenant_id'         => $tenantId,
                'permission_level'  => $invitation->permission_level,
                'status'            => 'active',
                'linked_at'         => now(),
                'linked_by_user_id' => $request->user()->id ?? null,
            ]);

            $invitation->update([
                'status'              => FirmInvitation::STATUS_ACCEPTED,
                'accepted_at'         => now(),
                'accepted_by_user_id' => $request->user()->id ?? null,
            ]);
        });

        Log::info('Tenant accepted firm-initiated invite', [
            'tenant_id' => $tenantId,
            'firm_id'   => $invitation->firm_id,
            'user_id'   => $request->user()->id,
        ]);

        return back()->with('success', 'Linked to your accountant. They can now access your books.');
    }

    public function declineIncoming(Request $request, int $invitationId): RedirectResponse
    {
        $tenantId = tenant('id');
        abort_unless($tenantId, 404);

        $invitation = FirmInvitation::query()
            ->where('id', $invitationId)
            ->where('tenant_id', $tenantId)
            ->where('direction', FirmInvitation::DIRECTION_FIRM_TO_CLIENT)
            ->where('status', FirmInvitation::STATUS_PENDING)
            ->first();
        abort_unless($invitation, 404);

        $invitation->update(['status' => FirmInvitation::STATUS_REVOKED]);

        return back()->with('success', 'Invitation declined.');
    }
}
