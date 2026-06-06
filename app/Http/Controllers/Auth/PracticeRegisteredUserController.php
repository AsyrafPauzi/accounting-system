<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Firm;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Onboarding for accountancy firms (Practice / Accountant track).
 *
 * Mirrors `RegisteredUserController` but produces:
 *   - 1 Firm row (central DB)
 *   - 1 owner User row tied to the firm (firm_id set, tenant_id null)
 *   - 1 firm-level Subscription row (tenant_id null, firm_id set) on
 *     the chosen Practice plan, defaulting to Practice Starter so the
 *     firm can play with the console immediately.
 *   - 1 firm-owner role assignment.
 *
 * The firm has no tenant DB of its own — they don't run books for
 * themselves; they manage clients. Initial onboarding does not link
 * any clients; that happens through invites or "create new client"
 * flows after first login.
 */
class PracticeRegisteredUserController extends Controller
{
    public function create(): Response
    {
        // Practice sign-up is a SaaS-only flow — self-hosted is
        // single-tenant by design (no firm hierarchy).
        abort_if(! \App\Support\Deployment::saasFeaturesEnabled(), 404);

        return Inertia::render('Auth/RegisterPractice', [
            'botGuard' => ['ts' => \App\Http\Middleware\SpamBotGuard::freshTimestamp()],
            'privacyVersion' => config('privacy.current_version'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(! \App\Support\Deployment::saasFeaturesEnabled(), 404);

        $validated = $request->validate([
            'firm_name'      => ['required', 'string', 'max:200'],
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password'       => ['required', 'confirmed', Rules\Password::defaults()],
            'accept_privacy' => ['accepted'],
        ], [
            'accept_privacy.accepted' => 'You must accept the privacy policy to create a firm account.',
        ]);

        // Default every new firm to the free Practice tier so they can
        // get into the console immediately. They pick a paid plan from
        // /practice/plan once they're inside.
        $plan = Plan::where('slug', 'practice-free')
            ->where('audience', 'practice')
            ->where('is_active', true)
            ->first();
        abort_unless($plan, 500, 'Practice Free plan is not seeded — run `php artisan db:seed --class=PlanSeeder`.');

        $firmRole = Role::where('name', 'firm-owner')->where('guard_name', 'web')->first();

        // Wrap in a transaction — if any step fails (e.g. duplicate
        // slug despite the unique check), we don't want a half-baked
        // firm row sitting around.
        [$firm, $user] = DB::transaction(function () use ($validated, $plan, $firmRole) {
            $slug = $this->makeUniqueSlug($validated['firm_name']);

            $firm = Firm::create([
                'name'          => $validated['firm_name'],
                'slug'          => $slug,
                'contact_email' => $validated['email'],
                'country'       => 'MY',
                'status'        => 'active',
            ]);

            $user = User::create([
                'name'                     => $validated['name'],
                'email'                    => $validated['email'],
                'password'                 => Hash::make($validated['password']),
                'tenant_id'                => null,           // firm users are central
                'firm_id'                  => $firm->id,
                'firm_role'                => 'owner',
                'role_id'                  => $firmRole?->id, // primary role pointer
                'privacy_accepted_at'      => now(),
                'privacy_accepted_version' => config('privacy.current_version'),
            ]);

            if ($firmRole) {
                $user->assignRole('firm-owner');
            }

            // Backfill firms.owner_user_id now that we have the user id.
            $firm->forceFill(['owner_user_id' => $user->id])->save();

            // Practice plans are paid; we still create a "trial"-style
            // active subscription so the console works immediately.
            // Toyyibpay checkout (a follow-up task) would convert this
            // to a real billing cycle.
            $sub = Subscription::create([
                'tenant_id'              => null,
                'firm_id'                => $firm->id,
                'plan_id'                => $plan->id,
                'status'                 => 'active',
                'interval'               => 'monthly',
                'current_period_start'   => now()->toDateString(),
                'current_period_ends_at' => now()->addMonth()->toDateString(),
                'gateway'                => 'system', // pending real gateway hook-up
            ]);

            $firm->forceFill(['firm_subscription_id' => $sub->id])->save();

            return [$firm, $user];
        });

        event(new Registered($user));
        Auth::login($user);

        Log::info('Practice firm signed up', [
            'firm_id' => $firm->id,
            'user_id' => $user->id,
            'plan'    => $plan->slug,
        ]);

        return redirect()->route('practice.dashboard');
    }

    /**
     * Best-effort unique slug. Tries the cleaned name, then appends a
     * numeric suffix until we find a free one. We deliberately keep
     * the loop bounded — anyone hitting >50 collisions is suspicious
     * and we'd rather 500 than spin.
     */
    private function makeUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'firm';
        if (! Firm::where('slug', $base)->exists()) {
            return $base;
        }
        for ($i = 2; $i < 50; $i++) {
            $candidate = $base . '-' . $i;
            if (! Firm::where('slug', $candidate)->exists()) {
                return $candidate;
            }
        }
        // Last resort: random suffix.
        return $base . '-' . Str::lower(Str::random(6));
    }
}
