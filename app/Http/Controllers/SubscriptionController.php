<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $tenantId = $user?->tenant_id;

        $plans = Plan::where('is_active', true)->get();

        $current = $tenantId
            ? Subscription::where('tenant_id', $tenantId)->active()->with('plan')->first()
            : null;

        return Inertia::render('Subscription/Index', [
            'plans' => $plans,
            'currentSubscription' => $current,
        ]);
    }

    public function checkout(Request $request)
    {
        $user = $request->user();
        $tenantId = $user?->tenant_id;

        $validated = $request->validate([
            'plan_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! Plan::where('is_active', true)->where('id', $value)->exists()) {
                        $fail('The selected plan is invalid.');
                    }
                },
            ],
            'interval' => ['required', 'in:monthly,yearly'],
        ]);

        $plan = Plan::where('is_active', true)->findOrFail($validated['plan_id']);

        $periodStart = now()->toDateString();
        $periodEnd = $validated['interval'] === 'yearly'
            ? now()->addYear()->toDateString()
            : now()->addMonth()->toDateString();

        Subscription::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'interval' => $validated['interval'],
                'current_period_start' => $periodStart,
                'current_period_ends_at' => $periodEnd,
                'gateway' => 'mock',
            ]
        );

        return redirect()->route('subscription.success')->with('success', 'Subscription activated successfully.');
    }

    public function success(): Response
    {
        $user = Auth::user();
        $tenantId = $user?->tenant_id;

        $subscription = $tenantId
            ? Subscription::where('tenant_id', $tenantId)->active()->with('plan')->first()
            : null;

        return Inertia::render('Subscription/Success', [
            'subscription' => $subscription,
        ]);
    }
}

