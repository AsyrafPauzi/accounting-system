<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Illuminate\Http\Request;
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
                'hasActiveSubscription' => function () use ($request) {
                    $user = $request->user();
                    if (! $user || ! $user->tenant_id) {
                        return false;
                    }

                    /** @var Tenant|null $tenant */
                    $tenant = Tenant::find($user->tenant_id);

                    return $tenant ? $tenant->hasActiveSubscription() : false;
                },
                'subscription_ends_at' => function () use ($request) {
                    $user = $request->user();
                    if (! $user || ! $user->tenant_id) {
                        return null;
                    }

                    /** @var Tenant|null $tenant */
                    $tenant = Tenant::find($user->tenant_id);
                    $subscription = $tenant?->activeSubscription();

                    return $subscription?->current_period_ends_at;
                },
                'planPermissions' => function () use ($request) {
                    $user = $request->user();
                    if (! $user || ! $user->tenant_id) {
                        return [];
                    }

                    /** @var Tenant|null $tenant */
                    $tenant = Tenant::find($user->tenant_id);
                    if (! $tenant) {
                        return [];
                    }

                    $subscription = $tenant->activeSubscription();
                    if (! $subscription || ! $subscription->plan) {
                        return [];
                    }

                    // Return permissions as an object: ['permission.name' => true, ...]
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
}
