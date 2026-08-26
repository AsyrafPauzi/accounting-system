<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Str; // <--- Add this line
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     *
     * In self-hosted single-tenant mode public registration is
     * disabled — the customer's admin creates additional users from
     * inside Settings → Team. Returning a 404 keeps the route
     * indistinguishable from a typo'd URL.
     */
    public function create()
    {
        abort_if(! \App\Support\Deployment::publicRegistrationEnabled(), 404);

        // Surface the trial duration on the form so the headline copy
        // ("14-day free Solo trial") matches what we'll actually grant
        // in store(). When the trial is disabled the form hides the
        // badge entirely instead of lying to the user.
        $trialDays = (bool) config('subscriptions.trial_enabled', true)
            ? max(0, (int) config('subscriptions.trial_days', 14))
            : 0;

        return Inertia::render('Auth/Register', [
            'botGuard' => ['ts' => \App\Http\Middleware\SpamBotGuard::freshTimestamp()],
            'privacyVersion' => config('privacy.current_version'),
            'trialDays' => $trialDays,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // Same guard as create(): public sign-up is SaaS-only.
        abort_if(! \App\Support\Deployment::publicRegistrationEnabled(), 404);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            // PDPA: consent must be explicit and tied to a policy version.
            // `accepted` rule covers true / "yes" / "1" / "on", which is what
            // a checked HTML checkbox sends.
            'accept_privacy' => ['accepted'],
        ], [
            'accept_privacy.accepted' => 'You must accept the privacy policy to create an account.',
        ]);


        $companyId = Str::slug($request->name) . '_' . rand(100, 999);
        $tenant = \App\Models\Tenant::create([
            'id' => $companyId,
            'provision_status' => 'pending',
        ]);


        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'tenant_id' => $companyId,
            'role_id' => $adminRole?->id,
            'privacy_accepted_at' => now(),
            'privacy_accepted_version' => config('privacy.current_version'),
        ]);

        if ($adminRole) {
            $user->assignRole('admin');
        }

        // 3. Plant the initial subscription.
        //
        // When the trial is enabled (default) we land new tenants on the
        // Solo tier with status="trialing" for `trial_days` days and queue
        // the free Startup tier as the pending plan. The existing
        // `subscription:apply-pending` cron then flips them to Startup
        // automatically when the trial ends — same code path as a manual
        // user-initiated downgrade, no special logic required.
        //
        // If the trial is disabled (or the configured trial plan can't be
        // resolved), we silently fall back to the historical "land on
        // Startup, period=1 month" behaviour so signup never breaks.
        $trialEnabled    = (bool) config('subscriptions.trial_enabled', true);
        $trialDays       = max(0, (int) config('subscriptions.trial_days', 14));
        $trialPlanSlug   = (string) config('subscriptions.trial_plan_slug', 'solo');
        $fallbackSlug    = (string) config('subscriptions.trial_fallback_slug', 'startup');

        $trialPlan    = $trialEnabled && $trialDays > 0
            ? Plan::where('slug', $trialPlanSlug)->where('is_active', true)->first()
            : null;
        $fallbackPlan = Plan::where('slug', $fallbackSlug)->first();

        if ($trialPlan && $fallbackPlan) {
            Subscription::create([
                'tenant_id'              => $tenant->id,
                'plan_id'                => $trialPlan->id,
                'pending_plan_id'        => $fallbackPlan->id,
                'pending_interval'       => 'monthly',
                'status'                 => 'trialing',
                'interval'               => 'monthly',
                'current_period_start'   => now()->toDateString(),
                'current_period_ends_at' => now()->addDays($trialDays)->toDateString(),
                'gateway'                => 'system',
            ]);

            Log::info('SME signup with Solo trial', [
                'tenant_id'    => $tenant->id,
                'trial_plan'   => $trialPlan->slug,
                'fallback'     => $fallbackPlan->slug,
                'trial_ends'   => now()->addDays($trialDays)->toDateString(),
            ]);
        } elseif ($fallbackPlan) {
            Subscription::create([
                'tenant_id'              => $tenant->id,
                'plan_id'                => $fallbackPlan->id,
                'status'                 => 'active',
                'interval'               => 'monthly',
                'current_period_start'   => now()->toDateString(),
                'current_period_ends_at' => now()->addMonth()->toDateString(),
                'gateway'                => 'system',
            ]);
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('provisioning', absolute: false));
    }
}
