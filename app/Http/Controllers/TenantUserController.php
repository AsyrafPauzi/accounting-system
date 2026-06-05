<?php

namespace App\Http\Controllers;

use App\Models\ExtraSeatPurchase;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ToyyibpayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class TenantUserController extends Controller
{
    /** Roles tenant admins may assign (excludes super-admin). */
    public const ASSIGNABLE_ROLES = ['admin', 'accountant', 'sales', 'viewer'];

    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;
        abort_if(! $tenantId, 404);

        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'email_verified_at', 'created_at'])
            ->map(function (User $u) use ($request) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'email_verified_at' => $u->email_verified_at?->toIso8601String(),
                    'roles' => $u->getRoleNames()->values()->all(),
                    'is_self' => $u->id === $request->user()->id,
                ];
            });

        return Inertia::render('Settings/Team', [
            'users' => $users,
            'assignableRoles' => self::ASSIGNABLE_ROLES,
            // Seat status drives the "Add user" panel: are we within the
            // included quota, or will the next add cost extra? The frontend
            // uses this to render an explicit consent banner.
            'seatStatus' => $this->buildSeatStatus($tenantId, $users->count()),
        ]);
    }

    public function store(\App\Http\Requests\StoreTenantUserRequest $request, ToyyibpayService $toyyibpay): RedirectResponse
    {
        $auth = $request->user();
        $tenantId = $auth->tenant_id;
        abort_if(! $tenantId, 404);

        $tenant = Tenant::find($tenantId);
        $subscription = $tenant?->activeSubscription();

        if (! $subscription) {
            return back()->with('error', 'No active subscription found. Please subscribe to a plan to add team members.');
        }

        $plan = $subscription->plan;
        $userCount = User::where('tenant_id', $tenantId)->count();
        $totalSeats = $subscription->totalSeats();
        $validated = $request->validated();

        // Already have headroom (included seats or previously purchased
        // extras) → create the user immediately, no payment dance.
        if ($userCount < $totalSeats) {
            return $this->createUserAndRedirect($tenantId, $validated, 'Team member added. Share the password with them securely or ask them to reset it from the login page.');
        }

        // Out of seats. If the plan doesn't sell extras, we can't help.
        if ((float) $plan->extra_user_price <= 0) {
            return back()->with('error', "User limit reached for your {$plan->name} plan. Upgrade your plan to add more members.");
        }

        // The form must explicitly opt-in to the charge. The frontend hides
        // the submit button until this checkbox is ticked, but we re-validate
        // here so curl/script abuse can't side-step the consent step.
        if (! ($validated['authorize_extra_seat_charge'] ?? false)) {
            return back()->withErrors([
                'authorize_extra_seat_charge' => "You're about to add a paid extra seat at RM " . number_format($plan->extra_user_price, 2) . "/month. Tick the authorise box to continue.",
            ])->withInput();
        }

        // Draft the purchase server-side so the password stays in our DB and
        // only the purchase id flows through Toyyibpay's payload.
        $purchase = ExtraSeatPurchase::create([
            'tenant_id'       => $tenantId,
            'subscription_id' => $subscription->id,
            'name'            => $validated['name'],
            'email'           => $validated['email'],
            'password_hash'   => Hash::make($validated['password']),
            'role'            => $validated['role'],
            'amount'          => $plan->extra_user_price,
            'currency'        => 'MYR',
            'status'          => ExtraSeatPurchase::STATUS_PENDING,
            'gateway'         => 'toyyibpay',
        ]);

        $paymentUrl = $toyyibpay->createBill([
            'billName'                => "Extra seat ({$plan->name})",
            'billDescription'         => "Adds {$validated['name']} as an extra team member on plan {$plan->name}.",
            'billAmount'              => (float) $plan->extra_user_price,
            'billReturnUrl'           => route('settings.team.index'),
            'billCallbackUrl'         => route('subscription.webhook.extra_user'),
            'billExternalReferenceNo' => 'seat-' . $purchase->id, // safe: only the id, no PII
            'billTo'                  => $auth->name,
            'billEmail'               => $auth->email,
            'billPhone'               => $auth->phone ?? '0123456789',
        ]);

        if (! $paymentUrl) {
            $purchase->update([
                'status'          => ExtraSeatPurchase::STATUS_FAILED,
                'failure_reason'  => 'Failed to create Toyyibpay bill (gateway error).',
            ]);

            Log::error('Extra-seat checkout failed: gateway returned no URL', [
                'tenant_id'   => $tenantId,
                'purchase_id' => $purchase->id,
            ]);

            return back()->with('error', 'Failed to initialize payment for the extra seat. Please try again or contact support.');
        }

        return Inertia::location($paymentUrl);
    }

    public function update(\App\Http\Requests\UpdateTenantUserRequest $request, User $user): RedirectResponse
    {
        $this->assertSameTenant($request->user(), $user);

        // Prevent users from changing their own role to avoid accidental lockout.
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['role' => 'You cannot change your own role. Please ask another administrator to do this for you.']);
        }

        $validated = $request->validated();

        if ($user->hasRole('admin') && $validated['role'] !== 'admin') {
            $adminCount = User::query()
                ->where('tenant_id', $user->tenant_id)
                ->role('admin')
                ->count();
            if ($adminCount <= 1) {
                return back()->withErrors(['role' => 'Assign another administrator before changing this user’s role.']);
            }
        }

        $targetRole = \App\Models\Role::where('name', $validated['role'])->where('guard_name', 'web')->first();
        if ($targetRole) {
            $user->update(['role_id' => $targetRole->id]);
            $user->syncRoles([$validated['role']]);
        }

        return redirect()->route('settings.team.index')->with('success', 'Role updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->assertSameTenant($request->user(), $user);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot remove your own account.');
        }

        if ($user->hasRole('admin')) {
            $adminCount = User::query()
                ->where('tenant_id', $user->tenant_id)
                ->role('admin')
                ->count();
            if ($adminCount <= 1) {
                return back()->with('error', 'You cannot delete the last administrator for this organization.');
            }
        }

        DB::transaction(function () use ($user) {
            $tenantId = $user->tenant_id;
            $tenant   = Tenant::find($tenantId);
            $subscription = $tenant?->activeSubscription();

            $user->delete();

            // If, after removal, the live user count has dipped *below* the
            // plan's included quota AND we had paid extras, refund one extra
            // seat so the tenant isn't billed for empty seats next renewal.
            if ($subscription && $subscription->extra_seats > 0) {
                $remaining = User::where('tenant_id', $tenantId)->count();
                $included  = (int) ($subscription->plan?->users_included ?? 1);

                if ($remaining < $included + $subscription->extra_seats) {
                    $subscription->decrement('extra_seats');

                    Log::info('Released one paid extra seat after user removal', [
                        'tenant_id'       => $tenantId,
                        'subscription_id' => $subscription->id,
                        'remaining_users' => $remaining,
                        'extra_seats_now' => $subscription->fresh()->extra_seats,
                    ]);
                }
            }
        });

        return redirect()->route('settings.team.index')->with('success', 'User removed from the team.');
    }

    /**
     * Compose the bundle the Team page needs to render the seat-status banner
     * and the conditional extra-seat consent UI.
     */
    private function buildSeatStatus(string $tenantId, int $userCount): array
    {
        $tenant = Tenant::find($tenantId);
        $subscription = $tenant?->activeSubscription();

        if (! $subscription || ! $subscription->plan) {
            return [
                'plan_name'         => null,
                'users_included'    => null,
                'extra_seats'       => 0,
                'extra_user_price'  => 0,
                'total_seats'       => $userCount,
                'used'              => $userCount,
                'next_user_charges' => false,
                'currency'          => 'MYR',
                'has_subscription'  => false,
            ];
        }

        $plan        = $subscription->plan;
        $totalSeats  = $subscription->totalSeats();
        $price       = (float) $plan->extra_user_price;

        return [
            'plan_name'         => $plan->name,
            'users_included'    => (int) $plan->users_included,
            'extra_seats'       => (int) $subscription->extra_seats,
            'extra_user_price'  => $price,
            'total_seats'       => $totalSeats,
            'used'              => $userCount,
            // True when the next add will trigger a charge. The frontend
            // uses this to flip the form into "consent mode".
            'next_user_charges' => $userCount >= $totalSeats && $price > 0,
            'currency'          => 'MYR',
            'has_subscription'  => true,
        ];
    }

    private function createUserAndRedirect(string $tenantId, array $validated, string $message): RedirectResponse
    {
        $targetRole = \App\Models\Role::where('name', $validated['role'])->where('guard_name', 'web')->first();

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'tenant_id' => $tenantId,
            'role_id'  => $targetRole?->id,
        ]);

        if ($targetRole) {
            $user->assignRole($validated['role']);
        }

        return redirect()->route('settings.team.index')->with('success', $message);
    }

    private function assertSameTenant(User $auth, User $target): void
    {
        if (! $auth->tenant_id || $auth->tenant_id !== $target->tenant_id) {
            abort(404);
        }
    }
}
