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
        $tenant = null;
        if ($user && $user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);
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
                'permissions' => $user ? $user->getAllPermissions()->pluck('name')->toArray() : [],
                'hasActiveSubscription' => function () use ($tenant) {
                    return $tenant ? $tenant->hasActiveSubscription() : false;
                },
                'subscription_ends_at' => function () use ($tenant) {
                    $subscription = $tenant?->activeSubscription();
                    return $subscription?->current_period_ends_at;
                },
                'planPermissions' => function () use ($tenant) {
                    if (! $tenant) {
                        return [];
                    }

                    $subscription = $tenant->activeSubscription();
                    if (! $subscription || ! $subscription->plan) {
                        return [];
                    }
                    return $subscription->plan->permissions->pluck('name')->mapWithKeys(function ($name) {
                        return [$name => true];
                    })->toArray();
                },
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'info'    => fn () => $request->session()->get('info'),
            ],
            'product_name' => config('app.product_name'),
            'product_tagline' => config('app.product_tagline'),
            'locale' => fn () => App::getLocale(),
            'translations' => fn () => $this->loadTranslations(App::getLocale()),
            'available_locales' => [
                ['code' => 'en', 'label' => 'English'],
                ['code' => 'ms', 'label' => 'Bahasa Malaysia'],
            ],
            'theme' => fn () => $request->user()?->theme_preference ?? 'light',
            'deployment_mode' => config('deployment.mode', 'saas'),
            'app_name' => function () use ($request) {
                $user = $request->user();
                if ($user && $user->tenant_id) {
                    $tenant = Tenant::find($user->tenant_id);
                    if ($tenant && $tenant->company) {
                        return $tenant->company['display_name'] ?? $tenant->company['legal_name'] ?? config('app.name');
                    }
                }
                return config('app.name');
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
}
