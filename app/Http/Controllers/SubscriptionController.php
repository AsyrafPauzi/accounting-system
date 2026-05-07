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
            'interval' => ['required', 'in:monthly,yearly,lifetime'],
        ]);

        $plan = Plan::where('is_active', true)->findOrFail($validated['plan_id']);

        // Check if tenant already has an active subscription that is NOT the free Startup plan
        $currentSubscription = Subscription::where('tenant_id', $tenantId)->active()->with('plan')->first();
        
        if ($currentSubscription && $currentSubscription->plan->slug !== 'startup') {
            return redirect()->back()->with('error', 'You already have an active paid subscription. It must expire before you can change your plan.');
        }

        $billAmount = $plan->priceForInterval($validated['interval']);

        // Handle Free Plans (Startup)
        if ($billAmount <= 0) {
            $subscription = Subscription::updateOrCreate(
                ['tenant_id' => $tenantId],
                [
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'interval' => $validated['interval'],
                    'gateway' => 'system',
                    'current_period_start' => now()->toDateString(),
                    'current_period_ends_at' => now()->addMonth()->toDateString(),
                ]
            );

            return redirect()->route('subscription.success')->with('success', 'Your subscription has been updated to the free plan.');
        }

        // Create or update a pending subscription for paid plans
        $subscription = Subscription::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'plan_id' => $plan->id,
                'status' => 'pending',
                'interval' => $validated['interval'],
                'gateway' => 'toyyibpay',
            ]
        );

        Log::info('Initiating subscription checkout', [
            'tenant_id' => $tenantId,
            'plan' => $plan->name,
            'interval' => $validated['interval'],
            'amount' => $billAmount
        ]);

        $paymentUrl = $toyyibpay->createBill([
            'billName' => "Subscription: {$plan->name}",
            'billDescription' => "{$plan->name} plan ({$validated['interval']}) for tenant {$tenantId}",
            'billAmount' => $billAmount,
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
                $periodEnd = match($subscription->interval) {
                    'lifetime' => null,
                    'yearly' => now()->addYear(),
                    default => now()->addMonth(),
                };

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

    public function webhookExtraUser(Request $request)
    {
        Log::info('Toyyibpay Extra User Webhook Received', $request->all());

        $statusId = $request->post('status_id');
        $externalRef = $request->post('order_id'); // JSON string in billExternalReferenceNo

        if ($statusId == 1 && $externalRef) {
            $data = json_decode($externalRef, true);
            
            if ($data && isset($data['tenant_id'], $data['email'])) {
                // Check if user already exists to avoid double creation
                if (! \App\Models\User::where('email', $data['email'])->exists()) {
                    $targetRole = \App\Models\Role::where('name', $data['role'])->where('guard_name', 'web')->first();
                    
                    $user = \App\Models\User::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'password' => \Illuminate\Support\Facades\Hash::make($data['password']),
                        'tenant_id' => $data['tenant_id'],
                        'role_id' => $targetRole?->id,
                    ]);

                    if ($targetRole) {
                        $user->assignRole($data['role']);
                    }

                    Log::info('Extra user created via webhook', ['user_id' => $user->id, 'tenant_id' => $data['tenant_id']]);
                }
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

    public function planSettings(Request $request): Response
    {
        $user = $request->user();
        $tenantId = $user?->tenant_id;
        abort_if(! $tenantId, 404);

        $subscription = Subscription::where('tenant_id', $tenantId)->active()->with('plan')->first();
        $userCount = \App\Models\User::where('tenant_id', $tenantId)->count();

        return Inertia::render('Settings/Plan', [
            'subscription' => $subscription,
            'userCount' => $userCount,
        ]);
    }
}

