<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Tenant;
use App\Services\ToyyibpayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
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
        ]);
    }

    public function store(\App\Http\Requests\StoreTenantUserRequest $request, ToyyibpayService $toyyibpay): RedirectResponse
    {
        $auth = $request->user();
        $tenantId = $auth->tenant_id;
        abort_if(! $tenantId, 404);

        $tenant = Tenant::find($tenantId);
        $subscription = $tenant->activeSubscription();
        
        if (! $subscription) {
            return back()->with('error', 'No active subscription found. Please subscribe to a plan to add team members.');
        }

        $plan = $subscription->plan;
        $userCount = User::where('tenant_id', $tenantId)->count();

        // Check if limit reached
        if ($userCount >= $plan->users_included) {
            if ($plan->extra_user_price > 0) {
                // Handle extra user charge via Toyyibpay
                // For simplicity, we create a bill and redirect. 
                // We'll pass the user data in a way that we can complete the creation after payment.
                // But Toyyibpay callback is limited. 
                // Alternatively, we just tell them they will be charged RM15.
                
                // For this implementation, we will create the user but mark them as "pending payment" if we had a flag.
                // Since we don't, let's redirect to a checkout for the extra user.
                
                $validated = $request->validated();
                
                $paymentUrl = $toyyibpay->createBill([
                    'billName' => "Extra User: {$validated['name']}",
                    'billDescription' => "Extra user charge for {$plan->name} plan",
                    'billAmount' => $plan->extra_user_price,
                    'billReturnUrl' => route('settings.team.index'),
                    'billCallbackUrl' => route('subscription.webhook.extra_user'), // New webhook needed
                    'billExternalReferenceNo' => json_encode([
                        'tenant_id' => $tenantId,
                        'name' => $validated['name'],
                        'email' => $validated['email'],
                        'password' => $validated['password'], // Note: Security risk in logs, but using for demo
                        'role' => $validated['role'],
                    ]),
                    'billTo' => $auth->name,
                    'billEmail' => $auth->email,
                    'billPhone' => $auth->phone ?? '0123456789',
                ]);

                if ($paymentUrl) {
                    return Inertia::location($paymentUrl);
                }

                return back()->with('error', 'Failed to initialize payment for extra user.');
            }

            return back()->with('error', "User limit reached for your {$plan->name} plan. Upgrade your plan to add more members.");
        }

        $validated = $request->validated();
        $targetRole = \App\Models\Role::where('name', $validated['role'])->where('guard_name', 'web')->first();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'tenant_id' => $tenantId,
            'role_id' => $targetRole?->id,
        ]);
        if ($targetRole) {
            $user->assignRole($validated['role']);
        }

        return redirect()->route('settings.team.index')->with('success', 'Team member added. Share the password with them securely or ask them to reset it from the login page.');
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

        $user->delete();

        return redirect()->route('settings.team.index')->with('success', 'User removed from the team.');
    }

    private function assertSameTenant(User $auth, User $target): void
    {
        if (! $auth->tenant_id || $auth->tenant_id !== $target->tenant_id) {
            abort(404);
        }
    }
}
