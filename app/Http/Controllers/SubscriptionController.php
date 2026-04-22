<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\ToyyibpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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

    public function checkout(Request $request, ToyyibpayService $toyyibpay)
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

        // Check if tenant already has an active subscription
        $existing = Subscription::where('tenant_id', $tenantId)->active()->exists();
        if ($existing) {
            return redirect()->back()->with('error', 'You already have an active subscription. It must expire before you can subscribe again.');
        }

        // Create or update a pending subscription
        $subscription = Subscription::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'plan_id' => $plan->id,
                'status' => 'pending',
                'interval' => $validated['interval'],
                'gateway' => 'toyyibpay',
            ]
        );

        $paymentUrl = $toyyibpay->createBill([
            'billName' => "Subscription: {$plan->name}",
            'billDescription' => "{$plan->name} plan ({$validated['interval']}) for tenant {$tenantId}",
            'billAmount' => $plan->priceForInterval($validated['interval']),
            'billReturnUrl' => route('subscription.callback'),
            'billCallbackUrl' => route('subscription.webhook'),
            'billExternalReferenceNo' => (string) $subscription->id,
            'billTo' => $user->name,
            'billEmail' => $user->email,
            'billPhone' => $user->phone ?? '0123456789', // Default phone if missing
        ]);

        if (! $paymentUrl) {
            return redirect()->back()->with('error', 'Failed to initialize payment with Toyyibpay. Please try again later.');
        }

        return Inertia::location($paymentUrl);
    }

    public function callback(Request $request)
    {
        $statusId = $request->query('status_id');
        $billCode = $request->query('billcode');

        if ($statusId == 1) {
            return redirect()->route('subscription.success')->with('success', 'Payment successful! Your subscription is being activated.');
        }

        if ($statusId == 2) {
            return redirect()->route('subscription.index')->with('error', 'Payment is pending. We will update your subscription once confirmed.');
        }

        return redirect()->route('subscription.index')->with('error', 'Payment failed or was canceled.');
    }

    public function webhook(Request $request)
    {
        Log::info('Toyyibpay Webhook Received', $request->all());

        $billCode = $request->post('billcode');
        $statusId = $request->post('status_id');
        $subscriptionId = $request->post('order_id'); // We passed subscription ID as billExternalReferenceNo

        if ($statusId == 1) {
            $subscription = Subscription::find($subscriptionId);

            if ($subscription) {
                $plan = $subscription->plan;
                
                $periodStart = now();
                $periodEnd = $subscription->interval === 'yearly'
                    ? now()->addYear()
                    : now()->addMonth();

                $subscription->update([
                    'status' => 'active',
                    'gateway_subscription_id' => $billCode,
                    'current_period_start' => $periodStart->toDateString(),
                    'current_period_ends_at' => $periodEnd->toDateString(),
                ]);

                Log::info('Subscription activated via webhook', ['subscription_id' => $subscription->id]);
            }
        }

        return response('OK');
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

