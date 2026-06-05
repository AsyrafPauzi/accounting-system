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

        // Order so the cards always render: Startup (free) → Solo → Growth →
        // Corporate → Enterprise. The is_contact_sales flag is sorted last
        // (Enterprise has price_monthly = 0 like Startup, so we'd otherwise
        // collide with the free tier on price alone).
        $plans = Plan::where('is_active', true)
            ->orderBy('is_contact_sales') // false (0) before true (1)
            ->orderBy('price_monthly')
            ->orderBy('id')
            ->get();

        $current = $tenantId
            ? Subscription::where('tenant_id', $tenantId)
                ->active()
                ->with(['plan', 'pendingPlan'])
                ->first()
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

        // Contact-sales tiers (Enterprise) don't have an automated checkout
        // path — those customers come through the sales pipeline and get a
        // negotiated contract / self-hosted deployment. The pricing page
        // already hides the "Choose plan" button for these, but a determined
        // user could still POST the plan_id directly.
        if ($plan->is_contact_sales) {
            return redirect()->back()->with('error', "{$plan->name} requires a custom contract. Please reach out to our sales team.");
        }

        $currentSubscription = Subscription::where('tenant_id', $tenantId)->active()->with('plan')->first();
        $billAmount = $plan->priceForInterval($validated['interval']);

        // Same plan + same interval = no-op so we don't double-charge.
        if (
            $currentSubscription
            && $currentSubscription->plan_id === $plan->id
            && $currentSubscription->interval === $validated['interval']
        ) {
            return redirect()->route('subscription.index')
                ->with('info', 'You\'re already on this plan and billing cadence.');
        }

        // Downgrade path — tenant is currently on a paid plan and is picking
        // a strictly cheaper one (or the free Startup tier). Don't take any
        // money now; just record the intent. The "subscription:apply-pending"
        // command flips the subscription on the day the current period ends
        // so the tenant keeps the features they paid for until then.
        if ($currentSubscription && $this->isDowngrade($currentSubscription, $plan, $validated['interval'])) {
            $currentSubscription->update([
                'pending_plan_id'  => $plan->id,
                'pending_interval' => $validated['interval'],
            ]);

            $effective = optional($currentSubscription->current_period_ends_at)->toDateString()
                ?? now()->addMonth()->toDateString();

            Log::info('Subscription downgrade scheduled', [
                'tenant_id'        => $tenantId,
                'current_plan'     => $currentSubscription->plan?->slug,
                'pending_plan'     => $plan->slug,
                'pending_interval' => $validated['interval'],
                'effective_date'   => $effective,
            ]);

            return redirect()->route('subscription.index')->with(
                'success',
                "Downgrade scheduled. You'll keep {$currentSubscription->plan->name} until {$effective}, then automatically switch to {$plan->name}."
            );
        }

        // Free plan and no current paid subscription — let them in directly.
        if ($billAmount <= 0) {
            Subscription::updateOrCreate(
                ['tenant_id' => $tenantId],
                [
                    'plan_id' => $plan->id,
                    'pending_plan_id' => null,
                    'pending_interval' => null,
                    'status' => 'active',
                    'interval' => $validated['interval'],
                    'gateway' => 'system',
                    'current_period_start' => now()->toDateString(),
                    'current_period_ends_at' => now()->addMonth()->toDateString(),
                ]
            );

            return redirect()->route('subscription.success')
                ->with('success', 'Your subscription has been updated to the free plan.');
        }

        // Block side-grade / upgrade between two paid plans for now: the
        // existing Toyyibpay flow is one-shot bills, not recurring, so we
        // can't cleanly proration-charge today. We tell the tenant to wait
        // for renewal (or schedule a downgrade) instead of silently taking
        // money on top of an active sub.
        if ($currentSubscription && $currentSubscription->plan->slug !== 'startup') {
            return redirect()->back()->with(
                'error',
                'You already have an active paid subscription. To upgrade now please email sales@bukucloud.com — or schedule a downgrade and we\'ll switch you on the renewal date.'
            );
        }

        // Brand-new paid subscription (or coming from Startup free): proceed
        // to Toyyibpay checkout.
        $subscription = Subscription::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'plan_id' => $plan->id,
                'pending_plan_id' => null,
                'pending_interval' => null,
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

    public function cancelPendingChange(Request $request)
    {
        $user = $request->user();
        $tenantId = $user?->tenant_id;
        abort_if(! $tenantId, 404);

        $sub = Subscription::where('tenant_id', $tenantId)->active()->first();
        if (! $sub || ! $sub->pending_plan_id) {
            return redirect()->route('subscription.index')
                ->with('info', 'No scheduled change to cancel.');
        }

        $sub->update([
            'pending_plan_id'  => null,
            'pending_interval' => null,
        ]);

        return redirect()->route('subscription.index')
            ->with('success', 'Scheduled plan change cancelled. You\'ll stay on your current plan when it renews.');
    }

    /**
     * Compare current and target plans to decide whether this is a downgrade.
     *
     * "Downgrade" = the tenant is on a paid plan today, and the target plan
     *   either (a) is the free Startup tier or (b) costs strictly less per
     *   month. Same plan + cheaper interval (e.g. monthly→yearly) is *not*
     *   a downgrade — that's a billing-cadence change which should still
     *   route through normal checkout.
     */
    private function isDowngrade(Subscription $current, Plan $target, string $targetInterval): bool
    {
        // Coming from the free tier → it's not a downgrade.
        if (! $current->plan || $current->plan->slug === 'startup') {
            return false;
        }

        // Target is the free tier from a paid one → always a downgrade.
        if ($target->slug === 'startup') {
            return true;
        }

        // Target is contact-sales → not handled here (controller already
        // refuses contact-sales checkout earlier).
        if ($target->is_contact_sales) {
            return false;
        }

        $currentMonthly = (float) $current->plan->price_monthly;
        $targetMonthly  = (float) $target->price_monthly;

        return $targetMonthly < $currentMonthly;
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
        // Toyyibpay payload is form-encoded; we redact the bill code on log
        // because it'd otherwise reveal which purchase is in flight.
        Log::info('Toyyibpay Extra User Webhook Received', [
            'order_id'  => $request->post('order_id'),
            'status_id' => $request->post('status_id'),
        ]);

        $statusId    = (int) $request->post('status_id');
        $externalRef = (string) $request->post('order_id', '');
        $billCode    = (string) $request->post('billcode', '');

        // The reference we sent during checkout is "seat-{purchase_id}" — a
        // stable opaque id that tells us which draft purchase to materialise.
        if (! preg_match('/^seat-(\d+)$/', $externalRef, $m)) {
            Log::warning('Extra-seat webhook ignored: malformed reference', ['ref' => $externalRef]);
            return response('OK');
        }

        $purchaseId = (int) $m[1];

        $purchase = \App\Models\ExtraSeatPurchase::find($purchaseId);
        if (! $purchase) {
            Log::warning('Extra-seat webhook ignored: purchase not found', ['purchase_id' => $purchaseId]);
            return response('OK');
        }

        // Idempotency: a webhook may be retried by the gateway. If the row is
        // already paid we just acknowledge and move on — no double-creation.
        if ($purchase->status === \App\Models\ExtraSeatPurchase::STATUS_PAID) {
            return response('OK');
        }

        // Non-success statuses → mark failed so the admin can retry.
        if ($statusId !== 1) {
            $purchase->update([
                'status'           => \App\Models\ExtraSeatPurchase::STATUS_FAILED,
                'gateway_bill_code' => $billCode ?: $purchase->gateway_bill_code,
                'failure_reason'   => 'Toyyibpay reported status_id=' . $statusId,
            ]);
            return response('OK');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($purchase, $billCode) {
            // Defence in depth: another admin may have invited the same email
            // through a parallel flow while the payment was in progress.
            $existing = \App\Models\User::where('email', $purchase->email)->first();
            if ($existing) {
                $purchase->update([
                    'status'            => \App\Models\ExtraSeatPurchase::STATUS_FAILED,
                    'gateway_bill_code' => $billCode ?: $purchase->gateway_bill_code,
                    'failure_reason'    => 'Email already registered before payment completed.',
                ]);
                Log::warning('Extra-seat purchase paid but email already exists', [
                    'purchase_id' => $purchase->id,
                    'email'       => $purchase->email,
                ]);
                return;
            }

            $targetRole = \App\Models\Role::where('name', $purchase->role)->where('guard_name', 'web')->first();

            $user = \App\Models\User::create([
                'name'      => $purchase->name,
                'email'     => $purchase->email,
                'password'  => $purchase->password_hash, // already hashed at draft time
                'tenant_id' => $purchase->tenant_id,
                'role_id'   => $targetRole?->id,
            ]);

            if ($targetRole) {
                $user->assignRole($purchase->role);
            }

            $purchase->update([
                'status'            => \App\Models\ExtraSeatPurchase::STATUS_PAID,
                'gateway_bill_code' => $billCode ?: $purchase->gateway_bill_code,
                'user_id'           => $user->id,
                'paid_at'           => now(),
                // Wipe the password hash from the draft once we've migrated
                // it to the user row — minimises blast radius if the table
                // is ever exfiltrated.
                'password_hash'     => '',
            ]);

            // Bump the subscription's paid-extras counter so the next bill
            // cycle reflects the new seat count.
            if ($purchase->subscription_id) {
                Subscription::where('id', $purchase->subscription_id)->increment('extra_seats');
            }

            Log::info('Extra seat granted via webhook', [
                'user_id'      => $user->id,
                'tenant_id'    => $purchase->tenant_id,
                'purchase_id'  => $purchase->id,
            ]);
        });

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

