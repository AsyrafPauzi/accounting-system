<?php

namespace App\Http\Controllers\Practice;

use App\Http\Controllers\Controller;
use App\Mail\FirmInviteExistingClient;
use App\Models\FirmClient;
use App\Models\FirmInvitation;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Firm-side "add a client" flows.
 *
 * Two ways a firm gets a new client into their console:
 *
 *   1. createNew — the firm has a brand-new client that isn't on
 *      BukuCloud yet. The firm enters basic info (company name, owner
 *      email, owner password). We provision a tenant exactly the way
 *      a self-signup would, set the owner up on the free Startup plan,
 *      and link them to the firm immediately. The owner can log in
 *      with the credentials the firm sets and run the books.
 *
 *   2. inviteExisting — the firm has a client who *is* already on
 *      BukuCloud (registered themselves). The firm enters their email;
 *      we create a `firm_invites_client` invitation; the SME sees a
 *      pending-invite banner inside their tenant Settings and accepts
 *      with one click, which links their tenant to the firm.
 *
 * Both flows respect the plan's `client_cap` — pending firm-initiated
 * invites count toward the cap so a firm can't queue 100 invites on a
 * 1-client plan.
 */
class AddClientController extends Controller
{
    public function show(Request $request): Response
    {
        $firm = $this->resolveFirm($request);

        return Inertia::render('Practice/AddClient', [
            'firm' => [
                'id'             => $firm->id,
                'name'           => $firm->name,
                'plan'           => $firm->subscription?->plan?->name,
                'plan_slug'      => $firm->subscription?->plan?->slug,
                'client_cap'     => $firm->clientCap(),     // null = unlimited
                'client_count'   => $firm->currentClientCount(),
                'remaining'      => $firm->clientsRemaining(), // null = unlimited
                'can_add'        => $firm->canAddClient(),
            ],
        ]);
    }

    public function createNew(Request $request): RedirectResponse
    {
        $firm = $this->resolveFirm($request);

        $this->guardCap($firm);

        $validated = $request->validate([
            'company_name'  => ['required', 'string', 'max:200'],
            'owner_name'    => ['required', 'string', 'max:255'],
            'owner_email'   => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class.',email'],
            'owner_password'=> ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $tenantId = Str::slug($validated['company_name']) . '_' . random_int(100, 999);
        $startupPlan = Plan::where('slug', 'startup')->first();
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        $linkedByUserId = $request->user()?->id;

        // Deliberately not wrapped in a DB transaction — user/tenant
        // rows commit on the central connection while ProvisionTenantJob
        // creates the tenant database asynchronously on the queue.
        $tenant = Tenant::create([
            'id' => $tenantId,
            'provision_status' => 'pending',
        ]);

        $user = User::create([
            'name'                     => $validated['owner_name'],
            'email'                    => $validated['owner_email'],
            'password'                 => Hash::make($validated['owner_password']),
            'tenant_id'                => $tenant->id,
            'role_id'                  => $adminRole?->id,
            // The firm consents to terms on the client's behalf. We
            // record this against the privacy version that was active
            // at the moment the firm filled the form so the audit trail
            // is accurate even if we ship a new policy tomorrow.
            'privacy_accepted_at'      => now(),
            'privacy_accepted_version' => config('privacy.current_version'),
        ]);

        if ($adminRole) {
            $user->assignRole('admin');
        }

        if ($startupPlan) {
            Subscription::create([
                'tenant_id'              => $tenant->id,
                'plan_id'                => $startupPlan->id,
                'status'                 => 'active',
                'interval'               => 'monthly',
                'gateway'                => 'system',
                'current_period_start'   => now()->toDateString(),
                'current_period_ends_at' => now()->addMonth()->toDateString(),
            ]);
        }

        FirmClient::create([
            'firm_id'           => $firm->id,
            'tenant_id'         => $tenant->id,
            'permission_level'  => 'admin',
            'status'            => 'active',
            'linked_at'         => now(),
            'linked_by_user_id' => $linkedByUserId,
        ]);

        Log::info('Practice: firm created new client', [
            'firm_id'    => $firm->id,
            'tenant_id'  => $tenant->id,
            'created_by' => $linkedByUserId,
        ]);

        return redirect()->route('practice.dashboard')->with(
            'success',
            "{$validated['company_name']} created and linked to your firm. The owner can log in with their email."
        );
    }

    public function inviteExisting(Request $request): RedirectResponse
    {
        $firm = $this->resolveFirm($request);
        $this->guardCap($firm);

        $validated = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));

        // Walk a few short branches in priority order so the firm gets
        // a useful error message for the most common mistakes.
        $target = User::where('email', $email)->first();
        if (! $target) {
            return back()->withErrors([
                'email' => "No BukuCloud account uses that email yet. Use the \"Create new client\" tab if they don't have an account.",
            ])->withInput();
        }
        if (! $target->tenant_id) {
            return back()->withErrors([
                'email' => 'That user is not associated with a business tenant — they may already work for a firm.',
            ])->withInput();
        }

        $existingLink = FirmClient::where('tenant_id', $target->tenant_id)
            ->where('status', 'active')
            ->first();
        if ($existingLink) {
            $belongsToYou = $existingLink->firm_id === $firm->id;
            return back()->withErrors([
                'email' => $belongsToYou
                    ? 'This client is already linked to your firm.'
                    : 'This client is already managed by another firm.',
            ])->withInput();
        }

        // Honour the SaaS super-admin's per-tenant feature toggle.
        // If the platform team has disabled the accountant feature for
        // this tenant, refuse the invite — even if the firm got hold
        // of the right email. Existing FirmClient links keep working
        // (toggling is non-destructive); only *new* invites are blocked.
        $targetTenant = Tenant::where('id', $target->tenant_id)->first();
        if ($targetTenant && (bool) ($targetTenant->practice_disabled ?? false)) {
            return back()->withErrors([
                'email' => 'The accountant feature has been disabled for this account by BukuCloud support. Ask the customer to contact support if this is unexpected.',
            ])->withInput();
        }

        $existingInvite = FirmInvitation::where('email', $email)
            ->where('firm_id', $firm->id)
            ->where('direction', FirmInvitation::DIRECTION_FIRM_TO_CLIENT)
            ->where('status', FirmInvitation::STATUS_PENDING)
            ->first();
        if ($existingInvite) {
            return back()->withErrors([
                'email' => 'You already have a pending invite for that email. They\'ll see it on their next login.',
            ])->withInput();
        }

        $invitation = FirmInvitation::create([
            'firm_id'          => $firm->id,
            'tenant_id'        => $target->tenant_id,
            'direction'        => FirmInvitation::DIRECTION_FIRM_TO_CLIENT,
            'email'            => $email,
            'token'            => FirmInvitation::generateToken(),
            'permission_level' => 'admin',
            'status'           => FirmInvitation::STATUS_PENDING,
            'expires_at'       => FirmInvitation::defaultExpiresAt(),
        ]);

        // Best-effort email delivery. The pending FirmInvitation is the
        // source of truth — if mail dispatch fails (no SMTP configured,
        // network blip, queue down) the SME still sees the invite on
        // their next sign-in. So we log the failure but never block
        // the firm-side success response on it.
        $emailDispatched = false;
        try {
            Mail::to($email)->queue(new FirmInviteExistingClient(
                firm: $firm,
                invitation: $invitation,
                inviterName: $request->user()?->name,
            ));
            $emailDispatched = true;
        } catch (\Throwable $e) {
            Log::warning('Practice: invite email dispatch failed', [
                'firm_id'   => $firm->id,
                'invited'   => $email,
                'tenant_id' => $target->tenant_id,
                'err'       => $e->getMessage(),
            ]);
        }

        Log::info('Practice: firm invited existing client', [
            'firm_id'         => $firm->id,
            'invited'         => $email,
            'tenant_id'       => $target->tenant_id,
            'email_dispatched'=> $emailDispatched,
        ]);

        return redirect()->route('practice.dashboard')->with(
            'success',
            $emailDispatched
                ? "Invite emailed to {$email}. They'll also see it inside BukuCloud on their next sign-in."
                : "Invite created for {$email}. We couldn't send the email right now, but they'll see it inside BukuCloud on their next sign-in."
        );
    }

    private function resolveFirm(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->isFirmOwner(), 403);
        $firm = $user->firm()->with('subscription.plan', 'clients', 'invitations')->first();
        abort_unless($firm, 403);
        return $firm;
    }

    private function guardCap($firm): void
    {
        if ($firm->canAddClient()) {
            return;
        }
        $cap = $firm->clientCap();

        // Self-hosted Enterprise has no plan picker — the cap comes
        // from the license. Bounce them back to where they came from
        // with an inline error so they can ask their license vendor
        // for an expansion instead of clicking "upgrade plan".
        if (\App\Support\Deployment::isSelfHosted()) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                redirect()->route('practice.dashboard')->with(
                    'error',
                    "You've hit your license tenant cap ({$cap}). Contact BukuCloud to expand your license to manage more clients."
                )
            );
        }

        // SaaS path — bounce to the plan picker.
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            redirect()->route('practice.plan')->with(
                'error',
                "You've hit your plan's client cap ({$cap}). Upgrade your practice plan to add more clients."
            )
        );
    }
}
