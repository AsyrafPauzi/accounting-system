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

        return Inertia::render('Auth/Register', [
            'botGuard' => ['ts' => \App\Http\Middleware\SpamBotGuard::freshTimestamp()],
            'privacyVersion' => config('privacy.current_version'),
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
        $tenant = \App\Models\Tenant::create(['id' => $companyId]);


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

        // 3. Automatically assign to Startup (Free) Plan
        $startupPlan = Plan::where('slug', 'startup')->first();
        if ($startupPlan) {
            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $startupPlan->id,
                'status' => 'active',
                'interval' => 'monthly',
                'current_period_start' => now()->toDateString(),
                'current_period_ends_at' => now()->addMonth()->toDateString(),
                'gateway' => 'system',
            ]);
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
