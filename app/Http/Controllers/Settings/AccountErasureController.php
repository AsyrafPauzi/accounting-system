<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PDPA right of erasure — flag the user (and their tenant if they're the
 * sole owner) for hard deletion after a cooling-off window. Actual
 * teardown is performed by the retention:purge command (see Phase 3
 * `retention:purge`); this controller only manages the user-visible
 * scheduling and cancellation UX.
 *
 * Why we don't delete immediately:
 *   - Income Tax Act 1967 forces 7-year retention for financial records.
 *     Hard-delete is a redaction step, not a row-level wipe, and that's
 *     non-trivial — better done in a single, audited batch by the
 *     retention command than ad-hoc in a request handler.
 *   - 30-day cooling-off is the industry norm and protects users from
 *     hostile takeovers (compromised session triggering deletion) and
 *     accidental clicks.
 */
class AccountErasureController extends Controller
{
    public const COOLING_OFF_DAYS = 30;

    public function show(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user, 403);

        $requestedAt = $user->deletion_requested_at;
        $scheduledAt = $requestedAt
            ? $requestedAt->copy()->addDays(self::COOLING_OFF_DAYS)
            : null;

        return Inertia::render('Settings/AccountErasure', [
            'isScheduled'        => (bool) $requestedAt,
            'requestedAt'        => optional($requestedAt)->toIso8601String(),
            'scheduledDeletionAt'=> optional($scheduledAt)->toIso8601String(),
            'coolingOffDays'     => self::COOLING_OFF_DAYS,
            'dpoEmail'           => config('privacy.dpo_email'),
            // Firm-owner block: if the user owns a firm with active
            // clients, scheduling deletion would orphan those clients.
            // We surface the count + a link so the UI can render an
            // explanatory banner before they hit the destructive button.
            'firmGuard' => $this->firmOwnerGuard($user),
        ]);
    }

    public function request(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        // Re-confirm password — preventing a hijacked session from
        // triggering deletion is the whole point of the cooling-off
        // window, but a password challenge is still cheap insurance.
        $request->validate([
            'password' => ['required', 'current_password'],
            'confirm'  => ['accepted'],
        ]);

        // A firm-owner with active clients would leave them orphaned
        // (firms.owner_user_id NULLed, FirmClient pivots dangling). Force
        // them to unlink first so the cleanup is explicit and audited.
        $guard = $this->firmOwnerGuard($user);
        if ($guard['blocked']) {
            return redirect()->route('settings.account_erase.show')->with(
                'error',
                'Your firm still manages '.$guard['active_client_count'].' active client'
                    .($guard['active_client_count'] === 1 ? '' : 's')
                    .'. Unlink them from the Practice console before deleting your account.'
            );
        }

        if ($user->deletion_requested_at) {
            return redirect()->route('settings.account_erase.show')
                ->with('info', 'Your account is already scheduled for deletion.');
        }

        $user->forceFill(['deletion_requested_at' => Carbon::now()])->save();

        Log::info('AccountErasure: scheduled', [
            'user_id'   => $user->id,
            'tenant_id' => $user->tenant_id,
            'firm_id'   => $user->firm_id,
        ]);

        return redirect()->route('settings.account_erase.show')
            ->with('success', 'Your account is scheduled for deletion. You can cancel within ' . self::COOLING_OFF_DAYS . ' days.');
    }

    /**
     * Resolve the firm-owner guard payload. Returns a flat shape both
     * the show() controller and the request() guard consume:
     *
     *   - is_firm_owner: true when the user owns a Firm row
     *   - active_client_count: number of FirmClient rows with status=active
     *   - blocked: true when deletion should be blocked (owner + > 0)
     *   - practice_dashboard_url: where to send them to do the unlinking
     *
     * Non-firm users get the same shape with `is_firm_owner: false` so
     * the React component doesn't have to defend against missing keys.
     */
    private function firmOwnerGuard($user): array
    {
        $isOwner = is_callable([$user, 'isFirmOwner']) ? $user->isFirmOwner() : false;

        $count = 0;
        if ($isOwner && $user->firm_id) {
            $count = \App\Models\FirmClient::query()
                ->where('firm_id', $user->firm_id)
                ->where('status', 'active')
                ->count();
        }

        return [
            'is_firm_owner'           => (bool) $isOwner,
            'active_client_count'     => $count,
            'blocked'                 => $isOwner && $count > 0,
            'practice_dashboard_url'  => route('practice.dashboard'),
        ];
    }

    public function cancel(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        if (! $user->deletion_requested_at) {
            return redirect()->route('settings.account_erase.show')
                ->with('info', 'No scheduled deletion to cancel.');
        }

        $user->forceFill(['deletion_requested_at' => null])->save();

        Log::info('AccountErasure: cancelled', [
            'user_id'   => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        return redirect()->route('settings.account_erase.show')
            ->with('success', 'Account deletion cancelled. Your data is safe.');
    }
}
