<?php

namespace App\Http\Controllers\Practice;

use App\Http\Controllers\Controller;
use App\Models\FirmClient;
use App\Models\FirmInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Acceptance flow for tenant→firm invites. Firm-owner clicks the link,
 * is asked to confirm, and on POST we create the FirmClient row.
 */
class FirmInvitationController extends Controller
{
    /**
     * Show the accept page. We render via Inertia so the firm-owner
     * sees a proper confirmation UI, not a raw GET-mutation link.
     */
    public function show(Request $request, string $token)
    {
        $invitation = $this->resolve($token);

        return inertia('Practice/AcceptInvite', [
            'invitation' => [
                'token'            => $invitation->token,
                'tenant_id'        => $invitation->tenant_id,
                'tenant_name'      => optional($invitation->tenant)->display_name
                    ?: optional($invitation->tenant)->legal_name
                    ?: $invitation->tenant_id,
                'permission_level' => $invitation->permission_level,
                'email'            => $invitation->email,
                'expires_at'       => $invitation->expires_at?->toIso8601String(),
            ],
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isFirmUser() && $user->isFirmOwner(), 403, 'Only firm owners can accept client invitations.');

        $invitation = $this->resolve($token);

        // Refuse if a firm already manages this tenant — a tenant
        // can only belong to one firm at a time.
        $existing = FirmClient::query()->where('tenant_id', $invitation->tenant_id)->where('status', 'active')->exists();
        if ($existing) {
            return redirect()->route('practice.dashboard')->with('error', 'That tenant is already managed by a firm.');
        }

        DB::transaction(function () use ($invitation, $user) {
            FirmClient::create([
                'firm_id'           => $user->firm_id,
                'tenant_id'         => $invitation->tenant_id,
                'permission_level'  => $invitation->permission_level,
                'status'            => 'active',
                'linked_at'         => now(),
                'linked_by_user_id' => $user->id,
            ]);

            $invitation->update([
                'firm_id'             => $user->firm_id,
                'status'              => FirmInvitation::STATUS_ACCEPTED,
                'accepted_at'         => now(),
                'accepted_by_user_id' => $user->id,
            ]);
        });

        Log::info('Practice: firm accepted client invite', [
            'firm_id'   => $user->firm_id,
            'tenant_id' => $invitation->tenant_id,
            'user_id'   => $user->id,
        ]);

        return redirect()->route('practice.dashboard')->with('success', 'Client linked. You can switch into their books from the dashboard.');
    }

    private function resolve(string $token): FirmInvitation
    {
        $invitation = FirmInvitation::query()
            ->where('token', $token)
            ->where('direction', FirmInvitation::DIRECTION_CLIENT_TO_FIRM)
            ->with('tenant')
            ->first();

        abort_unless($invitation, 404, 'Invitation not found.');

        if (! $invitation->isUsable()) {
            // Expired? Mark it so the next request returns the right state.
            if ($invitation->status === FirmInvitation::STATUS_PENDING && $invitation->expires_at?->isPast()) {
                $invitation->update(['status' => FirmInvitation::STATUS_EXPIRED]);
            }
            abort(410, 'This invitation is no longer valid.');
        }

        return $invitation;
    }
}
