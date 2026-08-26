<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Per-request memo. We deliberately don't use the Cache facade because
     * Stancl Tenancy wraps it with a tag-based store wrapper, and the
     * database cache driver doesn't support tagging. Reading the JSON
     * file once per request is cheap (~1ms) so a static memo is enough.
     *
     * @var array<string, array>
     */
    protected static array $translationsMemo = [];

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        // Resolve the tenant ONCE per request. The previous implementation
        // called Tenant::find twice on every page load (once for auth /
        // subscription props, once for app_name). Memoising via $tenant
        // halves that DB cost.
        //
        // Two slightly different shapes here:
        //   - SME tenant users: $tenant comes from $user->tenant_id
        //   - Firm users acting on a client: $tenant comes from
        //     `acting_tenant_id` in session. We still want plan
        //     permissions / branding / app name to reflect the *client*
        //     they're inside, not the firm.
        $tenant = null;
        if ($user) {
            if ($user->isFirmUser()) {
                $actingTenantId = $request->session()->get('acting_tenant_id');
                if ($actingTenantId) {
                    $tenant = Tenant::find($actingTenantId);
                }
            } elseif ($user->tenant_id) {
                $tenant = Tenant::find($user->tenant_id);
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'impersonator_id' => $request->session()->get('impersonator_id'),
                'teamPermissions' => [
                    'view' => $user?->can('users.view') ?? false,
                    'create' => $user?->can('users.create') ?? false,
                    'edit' => $user?->can('users.edit') ?? false,
                    'delete' => $user?->can('users.delete') ?? false,
                ],
                // Lazy: not every page calls hasPermission() and Inertia
                // partial reloads can skip resolving permissions entirely.
                //
                // For firm users acting on a client, mirror the
                // `Gate::before` projection in AppServiceProvider —
                // they effectively hold every tenant-level permission
                // (everything except `admin.*` and `practice.*`) once
                // their FirmClient pivot has been resolved by
                // InitializeTenancyByLoggedInUser. The sidebar reads
                // this array directly, so without the projection menu
                // items would hide even though the route handlers
                // themselves accept the request.
                'permissions' => fn () => $this->projectedPermissions($user),
                // Self-hosted has no subscription concept — the customer
                // bought a license, the running app *is* the entitlement.
                // Returning true short-circuits the subscription nags &
                // paid-only link gating on the SME-side UI.
                'hasActiveSubscription' => fn () => \App\Support\Deployment::isSelfHosted()
                    ? true
                    : ($tenant ? $tenant->hasActiveSubscription() : false),
                'subscription_ends_at' => function () use ($tenant) {
                    if (\App\Support\Deployment::isSelfHosted()) return null;
                    $subscription = $tenant?->activeSubscription();
                    return $subscription?->current_period_ends_at;
                },
                'planPermissions' => function () use ($tenant) {
                    // Self-hosted unlocks every feature — license tier
                    // gating happens at the LicenseService layer, not
                    // via the Plan permissions table. Returning every
                    // perm as true is the simplest universal grant.
                    if (\App\Support\Deployment::isSelfHosted()) {
                        return \App\Models\Permission::query()
                            ->pluck('name')
                            ->mapWithKeys(fn ($n) => [$n => true])
                            ->toArray();
                    }
                    if (! $tenant) return [];
                    $subscription = $tenant->activeSubscription();
                    if (! $subscription || ! $subscription->plan) return [];
                    return $subscription->plan->permissions->pluck('name')->mapWithKeys(
                        fn ($name) => [$name => true]
                    )->toArray();
                },
                // Trial state for the active SME tenant. Null for self-
                // hosted, firm users, anonymous, and tenants whose
                // subscription is not in `trialing` status — which lets
                // every component do `auth.trial && <Banner />` without
                // re-checking. Firm users get their trial info via the
                // separate `practice` block below if we ever add firm
                // trials.
                'trial' => function () use ($tenant) {
                    if (\App\Support\Deployment::isSelfHosted() || ! $tenant) {
                        return null;
                    }
                    $sub = $tenant->activeSubscription()?->loadMissing(['plan', 'pendingPlan']);
                    if (! $sub || $sub->status !== 'trialing') {
                        return null;
                    }
                    $endsAt = $sub->current_period_ends_at;
                    $daysLeft = null;
                    if ($endsAt) {
                        $end = $endsAt instanceof \Carbon\Carbon
                            ? $endsAt->copy()->endOfDay()
                            : \Carbon\Carbon::parse((string) $endsAt)->endOfDay();
                        $daysLeft = max(0, (int) ceil(now()->diffInRealSeconds($end, false) / 86400));
                    }
                    return [
                        'plan_slug'      => $sub->plan?->slug,
                        'plan_name'      => $sub->plan?->name,
                        'ends_at'        => $endsAt?->toDateString(),
                        'days_left'      => $daysLeft,
                        'fallback_name'  => $sub->pendingPlan?->name,
                        'fallback_slug'  => $sub->pendingPlan?->slug,
                    ];
                },
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'info'    => fn () => $request->session()->get('info'),
                'new_customer_id' => fn () => $request->session()->get('new_customer_id'),
                // One-shot license payload from /admin/self-hosted issue.
                // We never persist the signed key in the DB, so this is
                // the only place the operator sees it. Strict per-request
                // — Laravel's flash machinery clears it on the next req.
                'issued_license' => fn () => $request->session()->get('issued_license'),
                'issued_api_key' => fn () => $request->session()->get('issued_api_key'),
                'issued_signing_key' => fn () => $request->session()->get('issued_signing_key'),
            ],
            'product_name' => config('app.product_name'),
            'product_tagline' => config('app.product_tagline'),
            'locale' => fn () => App::getLocale(),
            'translations' => fn () => $this->loadTranslations(App::getLocale()),
            'available_locales' => [
                ['code' => 'en', 'label' => 'English'],
                ['code' => 'ms', 'label' => 'Bahasa Malaysia'],
            ],
            'theme' => fn () => $user?->theme_preference ?? 'light',
            'deployment_mode' => config('deployment.mode', 'saas'),
            'copilot_credits' => function () use ($user, $tenant) {
                if (! $user || ! $tenant) {
                    return null;
                }
                if (! ($user->can('copilot.use') ?? false)) {
                    return null;
                }
                try {
                    return app(\App\Services\Copilot\CopilotCreditService::class)->snapshot($tenant);
                } catch (\Throwable) {
                    return null;
                }
            },
            // Self-hosted update notification. Populated only when:
            //   1. We're running in self_hosted mode
            //   2. The publisher's heartbeat response has advertised a
            //      version string different from our current one
            // SaaS instances always get null — they're managed.
            'self_hosted_update' => function () {
                if (! \App\Support\Deployment::isSelfHosted()) {
                    return null;
                }
                $advertised = \App\Models\PlatformSetting::get('latest_available_version');
                if (! $advertised) {
                    return null;
                }
                $current = (string) config('app.version', 'unknown');
                if ($advertised === $current) {
                    return null;
                }
                return [
                    'current_version'     => $current,
                    'available_version'   => $advertised,
                    'notes'               => \App\Models\PlatformSetting::get('latest_update_notes'),
                    'url'                 => \App\Models\PlatformSetting::get('latest_update_url'),
                ];
            },
            // Practice / Accountant track context — only populated for
            // firm users; null for normal SME tenant users so the
            // front-end can do `practice && <Banner />` cleanly.
            'practice' => function () use ($user, $request, $tenant) {
                if (! $user || ! $user->isFirmUser()) {
                    return null;
                }
                $firm = $user->firm;
                $actingTenantId = $request->session()->get('acting_tenant_id');
                // Firm subscription info — drives the sidebar plan
                // badge ("Practice Starter" / "Practice Free") and the
                // "Upgrade" link target for firm users. Without this,
                // the SME-side "Free tier" nag mis-fires because firm
                // users have no tenant subscription by design.
                // Self-hosted installs don't model SaaS Practice plans —
                // entitlement comes from the license, not from a
                // Subscription row. Skip the (always-null) DB query so
                // every page render shaves one trip.
                $firmSub = null;
                if ($firm && \App\Support\Deployment::isSaas()) {
                    $firmSub = \App\Models\Subscription::query()
                        ->where('firm_id', $firm->id)
                        ->whereNull('tenant_id')
                        ->with('plan')
                        ->latest('id')
                        ->first();
                }
                return [
                    'firm' => $firm ? [
                        'id'   => $firm->id,
                        'name' => $firm->name,
                        'role' => $user->firm_role,
                    ] : null,
                    'is_inside_client' => (bool) $actingTenantId,
                    'acting_client'    => $actingTenantId && $tenant ? [
                        'tenant_id' => $tenant->id,
                        'name'      => $tenant->display_name ?: ($tenant->legal_name ?: $tenant->id),
                    ] : null,
                    'subscription' => $firmSub ? [
                        'plan_name' => $firmSub->plan?->name ?? 'Practice',
                        'plan_slug' => $firmSub->plan?->slug,
                        'is_free'   => $firmSub->plan?->slug === 'practice-free',
                        'is_active' => $firmSub->isActive(),
                    ] : null,
                ];
            },
            // SME-side: how many firm-initiated invites are pending for
            // this tenant. We expose just the count so layouts can flag
            // the Settings menu without leaking firm names everywhere.
            'pending_firm_invites_count' => function () use ($user, $tenant) {
                if (! $user || ! $tenant || $user->isFirmUser()) {
                    return 0;
                }
                return \App\Models\FirmInvitation::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('direction', \App\Models\FirmInvitation::DIRECTION_FIRM_TO_CLIENT)
                    ->where('status', \App\Models\FirmInvitation::STATUS_PENDING)
                    ->count();
            },
            'app_name' => function () use ($tenant) {
                if ($tenant && $tenant->company) {
                    return $tenant->company['display_name']
                        ?? $tenant->company['legal_name']
                        ?? config('app.name');
                }
                return config('app.name');
            },
            'company_flags' => [
                'show_goods_flow' => ! $tenant || $tenant->show_goods_flow !== false,
            ],
            'onboarding_checklist' => function () use ($user, $tenant) {
                if (! $user || ! $tenant || $user->isFirmUser()) {
                    return null;
                }

                return \App\Support\OnboardingChecklist::forUser($user, $tenant);
            },
        ];
    }

    /**
     * Load and merge translation JSON for the active locale, falling back to en
     * for any keys not yet translated. Memoised per-request only.
     */
    protected function loadTranslations(string $locale): array
    {
        if (isset(static::$translationsMemo[$locale])) {
            return static::$translationsMemo[$locale];
        }

        $en = $this->readLangFile('en');
        if ($locale === 'en') {
            return static::$translationsMemo[$locale] = $en;
        }

        $other = $this->readLangFile($locale);
        return static::$translationsMemo[$locale] = array_replace_recursive($en, $other);
    }

    protected function readLangFile(string $code): array
    {
        $path = lang_path("{$code}.json");
        if (! is_file($path)) {
            return [];
        }
        $contents = file_get_contents($path);
        $data = json_decode($contents, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Project the user's effective permission set, taking into account
     * the firm-user "act as client" path.
     *
     * Server-side authorisation lives in `Gate::before` (see
     * AppServiceProvider): firm users with `practice.access`, currently
     * acting on a client tenant (tenancy initialised), are granted
     * every ability except `admin.*` and `practice.*`. The frontend
     * sidebar / button gates read `auth.permissions` as a flat array,
     * so without this mirror the menu items would hide even though
     * the routes themselves accept the request.
     *
     * Permission filtering by FirmClient permission_level (admin /
     * editor / viewer) mirrors Gate::before via FirmActingPermissions.
     */
    protected function projectedPermissions($user): array
    {
        if (! $user) {
            return [];
        }

        $own = $user->getAllPermissions()->pluck('name')->toArray();

        if (! method_exists($user, 'isFirmUser') || ! $user->isFirmUser()) {
            return $own;
        }

        // Mirror Gate::before short-circuits: must hold practice.access
        // and tenancy must actually be initialised (i.e. the firm-user
        // is currently *inside* a client tenant).
        $hasAccess = in_array('practice.access', $own, true);
        $tenancyOn = function_exists('tenancy') && tenancy()->initialized;
        if (! $hasAccess || ! $tenancyOn) {
            return $own;
        }

        $level = \App\Models\FirmClient::query()
            ->where('firm_id', $user->firm_id)
            ->where('tenant_id', tenant('id'))
            ->where('status', 'active')
            ->value('permission_level');

        $tenantWide = \App\Support\FirmActingPermissions::allowedForLevel($level ?? 'viewer');

        return array_values(array_unique(array_merge($own, $tenantWide)));
    }
}
