<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StorePlanRequest;
use App\Http\Requests\Admin\UpdatePlanRequest;
use App\Models\Plan;
use App\Services\AdminPlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPlanController extends Controller
{
    public function __construct(private AdminPlanService $planService) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);

        $plans = Plan::with('permissions')
            ->withCount('subscriptions')
            ->orderBy('price_monthly')
            ->get()
            ->map(fn (Plan $plan) => [
                'id'                 => $plan->id,
                'name'               => $plan->name,
                'slug'               => $plan->slug,
                'price_monthly'      => $plan->price_monthly,
                'price_yearly'       => $plan->price_yearly,
                'users_included'     => $plan->users_included,
                'extra_user_price'   => $plan->extra_user_price,
                'is_active'          => $plan->is_active,
                'subscriptions_count' => $plan->subscriptions_count,
                'permissions'        => $plan->permissions->pluck('name')->toArray(),
            ]);

        return Inertia::render('Admin/Plans/Index', [
            'plans' => $plans,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);

        return Inertia::render('Admin/Plans/Form', [
            'plan'               => null,
            'permissionsGrouped' => $this->planService->availablePermissionsGrouped(),
        ]);
    }

    public function store(StorePlanRequest $request): RedirectResponse
    {
        $this->planService->create($request->validated());

        return redirect()->route('admin.plans.index')
            ->with('success', 'Plan created successfully.');
    }

    public function edit(Request $request, Plan $plan): Response
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);

        $plan->load('permissions');

        return Inertia::render('Admin/Plans/Form', [
            'plan' => [
                'id'               => $plan->id,
                'name'             => $plan->name,
                'slug'             => $plan->slug,
                'price_monthly'    => $plan->price_monthly,
                'price_yearly'     => $plan->price_yearly,
                'users_included'   => $plan->users_included,
                'extra_user_price' => $plan->extra_user_price,
                'features'         => $plan->features ?? [],
                'is_active'        => $plan->is_active,
                'permissions'      => $plan->permissions->pluck('name')->toArray(),
            ],
            'permissionsGrouped' => $this->planService->availablePermissionsGrouped(),
        ]);
    }

    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        $activeSubscribers = $plan->subscriptions()->active()->count();

        if ($activeSubscribers > 0 && ! $request->boolean('is_active', true)) {
            return redirect()->back()
                ->with('error', "Cannot deactivate plan \"{$plan->name}\" — it has {$activeSubscribers} active subscriber(s). Reassign them first.");
        }

        $this->planService->update($plan, $request->validated());

        return redirect()->route('admin.plans.index')
            ->with('success', "Plan \"{$plan->name}\" updated.");
    }
}
