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
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        
        $companyId = Str::slug($request->name) . '_' . rand(100, 999);
        $tenant = \App\Models\Tenant::create(['id' => $companyId]);

        
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();

        // 2. Create the User and link them to that Tenant
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'tenant_id' => $companyId, // <--- Link established
            'role_id' => $adminRole?->id,
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
