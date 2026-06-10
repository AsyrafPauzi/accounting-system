<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\BillingHistoryService;
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
            ->where('audience', 'sme') // Practice plans live on /register/practice
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
        //
        // Trialing tenants are explicitly NOT blocked here: their
        // subscription is technically on Corporate but no money has changed
        // hands, so converting mid-trial to any paid plan is a clean
        // checkout. We skip the side-grade refusal for them.
        $isTrialing = $currentSubscription && $currentSubscription->status === 'trialing';
        if ($currentSubscription && $currentSubscription->plan->slug !== 'startup' && ! $isTrialing) {
            return redirect()->back()->with(
                'error',
                'You already have an active paid subscription. To upgrade now please email sales@bukucloud.com — or schedule a downgrade and we\'ll switch you on the renewal date.'
            );
        }

        // Brand-new paid subscription (or coming from Startup free / a
        // mid-trial conversion): proceed to Toyyibpay checkout.
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
        // Trialing subscriptions are not "actively on" their trial plan —
        // no payment has been taken, no commitment made. Picking any
        // paid plan mid-trial is a paid conversion (force fresh checkout),
        // not a downgrade. Picking the free tier still routes through the
        // schedule path so the tenant gets the same "stays on Corporate
        // until trial-end then auto-switches" UX they got at signup.
        if ($current->status === 'trialing' && (float) $target->price_monthly > 0.0) {
            return false;
        }

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
                // Practice (and SME, when we extend it) upgrade flow parks
                // the target plan in `pending_plan_id` / `pending_interval`
                // and keeps the current plan active so the customer
                // doesn't lose access while paying. On payment success
                // we swap the pending fields into the live ones in a
                // single update so the cutover is atomic.
                $effectiveInterval = $subscription->pending_interval
                    ?: $subscription->interval;

                $periodStart = now();
                $periodEnd = match($effectiveInterval) {
                    'lifetime' => null,
                    'yearly' => now()->addYear(),
                    default => now()->addMonth(),
                };

                $updates = [
                    'status'                  => 'active',
                    'gateway_subscription_id' => $billCode,
                    'interval'                => $effectiveInterval,
                    'current_period_start'    => $periodStart->toDateString(),
                    'current_period_ends_at'  => $periodEnd?->toDateString(),
                ];

                if ($subscription->pending_plan_id) {
                    $updates['plan_id']         = $subscription->pending_plan_id;
                    $updates['pending_plan_id'] = null;
                }
                if ($subscription->pending_interval) {
                    $updates['pending_interval'] = null;
                }

                $subscription->update($updates);

                Log::info('Subscription activated via webhook', [
                    'subscription_id' => $subscription->id,
                    'plan_id'         => $subscription->fresh()->plan_id,
                    'interval'        => $effectiveInterval,
                ]);
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
        // Self-hosted: this page shows the *license* (entitlement, expiry,
        // heartbeat health, renewal contact) instead of a SaaS subscription.
        // There is no upgrade flow on a customer-owned install — bumps in
        // user / tenant cap come from re-issuing the license server-side.
        if (\App\Support\Deployment::isSelfHosted()) {
            return $this->licenseSettings($request);
        }

        $user = $request->user();

        // SaaS firm-owners (Practice / Accountant track) don't have a
        // tenant — their Subscription lives on the firm. Render a
        // firm-side plan summary so they can see "Practice Starter
        // — 5 client cap, 2 in use, renews on 12 Aug" without needing
        // to dig into the Practice console upgrade page.
        if ($user && method_exists($user, 'isFirmUser') && $user->isFirmUser()) {
            return $this->firmPlanSettings($request);
        }

        $tenantId = $user?->tenant_id;
        abort_if(! $tenantId, 404);

        // Eager-load pendingPlan because the trial banner on Settings/Plan
        // names the fallback plan ("Auto-switches to Startup on …"). Same
        // relation Subscription/Index already hydrates for the scheduled-
        // change banner, kept consistent here so the UI reads one shape.
        $subscription = Subscription::where('tenant_id', $tenantId)
            ->active()
            ->with(['plan', 'pendingPlan'])
            ->first();
        $userCount = \App\Models\User::where('tenant_id', $tenantId)->count();

        return Inertia::render('Settings/Plan', [
            'subscription' => $subscription,
            'userCount' => $userCount,
            'history' => app(BillingHistoryService::class)->forSubscription($subscription),
        ]);
    }

    /**
     * Firm-owner Plan & Usage view (SaaS only). Shows the firm's
     * Practice plan, current client count vs cap, and renewal info.
     * Self-hosted Enterprise firm-owners hit `licenseSettings()`
     * higher up so they never reach here.
     */
    private function firmPlanSettings(Request $request): Response
    {
        $user = $request->user();
        $firm = $user?->firm()->with('subscription.plan')->first();
        abort_unless($firm, 404);

        $sub = $firm->subscription;
        $plan = $sub?->plan;

        // Firm-staff seat usage. Mirrors the SME page's "1 of 5 seats
        // used" widget but counts users with this firm_id (not
        // tenant_id), since firm staff don't belong to a tenant.
        $staffCount = \App\Models\User::query()
            ->where('firm_id', $firm->id)
            ->whereNotNull('firm_role')
            ->count();

        $extraSeats = (int) ($sub?->extra_seats ?? 0);

        return Inertia::render('Settings/PlanFirm', [
            'firm' => [
                'id'   => $firm->id,
                'name' => $firm->name,
            ],
            'history' => app(BillingHistoryService::class)->forSubscription($sub),
            'subscription' => $sub ? [
                'plan_name'              => $plan?->name ?? 'Practice',
                'plan_slug'              => $plan?->slug,
                'is_free'                => $plan?->slug === 'practice-free',
                'status'                 => $sub->status,
                'interval'               => $sub->interval,
                'current_period_start'   => $sub->current_period_start,
                'current_period_ends_at' => $sub->current_period_ends_at,
                'price_monthly'          => $plan?->price_monthly,
                'price_yearly'           => $plan?->price_yearly,
                'extra_user_price'       => $plan?->extra_user_price,
                'extra_seats'            => $extraSeats,
                'users_included'         => (int) ($plan?->users_included ?? 1),
                // Marketing bullets — same shape the SME page renders
                // so the layout can use one component for both.
                'features'               => $plan?->features ?? [],
                'gateway'                => $sub->gateway,
            ] : null,
            'usage' => [
                'client_count' => $firm->currentClientCount(),
                'client_cap'   => $firm->clientCap(), // null = unlimited
                'remaining'    => $firm->clientsRemaining(),
                'staff_count'  => $staffCount,
            ],
        ]);
    }

    /**
     * Self-hosted license read-out. We deliberately don't expose the raw
     * signed key — only its claims and live status — so a screenshot of
     * this page can't be used to provision another install.
     *
     * Renewal contact information is sourced from config/deployment.php
     * (`vendor_contact_email` / `vendor_contact_url`) so a reseller /
     * SI partner can override it without forking the page.
     */
    private function licenseSettings(Request $request): Response
    {
        $svc    = app(\App\Services\Licensing\LicenseService::class);
        $status = $svc->status();
        $claims = $status['claims'] ?? null;

        $now       = \Illuminate\Support\Carbon::now();
        $expiresAt = ! empty($claims['expires_at'])
            ? \Illuminate\Support\Carbon::parse($claims['expires_at'])
            : null;
        $issuedAt  = ! empty($claims['issued_at'])
            ? \Illuminate\Support\Carbon::parse($claims['issued_at'])
            : null;

        // Negative values mean "already expired"; we keep the sign so the
        // UI can render "12 days overdue" without an extra branch.
        $daysLeft = $expiresAt ? (int) round($now->diffInDays($expiresAt, false)) : null;

        $features = is_array($claims['features'] ?? null) ? array_values($claims['features']) : [];

        // Heartbeat health drives a small "you've been offline for N days"
        // chip on the page so the operator knows their install can still
        // phone home; we read the same cache key the gate middleware uses.
        $lastHeartbeatIso = \Illuminate\Support\Facades\Cache::get(
            \App\Console\Commands\SelfHostedHeartbeat::LAST_OK_KEY
        );

        // Usage counts. Wrapped in try/catch so the page renders even
        // if a freshly-bootstrapped install hasn't run all migrations
        // yet — we don't want a partial state to hide the renewal info.
        $tenantId = $request->user()?->tenant_id;
        try {
            $userCount = $tenantId
                ? \App\Models\User::where('tenant_id', $tenantId)->count()
                : \App\Models\User::query()->count();
        } catch (\Throwable $e) {
            $userCount = 0;
        }
        try {
            $tenantCount = \App\Models\Tenant::query()->count();
        } catch (\Throwable $e) {
            $tenantCount = 0;
        }

        // Local "you should update" advertisement — heartbeat persists
        // these from the publisher into platform_settings, so we just
        // read them back. Null when no banner is broadcast (or when
        // the table isn't available, e.g. mid-migration).
        try {
            $latestVersion = \App\Models\PlatformSetting::get('latest_available_version');
        } catch (\Throwable $e) {
            $latestVersion = null;
        }
        $currentVersion = (string) (config('app.version') ?? env('APP_VERSION', '1.0.0'));

        return Inertia::render('Settings/PlanSelfHosted', [
            'license' => [
                'status'         => $status['status'] ?? 'missing',
                'customer_id'    => $claims['customer_id']   ?? null,
                'customer_name'  => $claims['customer_name'] ?? null,
                'plan_tier'      => $claims['plan_tier']     ?? null,
                'max_users'      => isset($claims['max_users'])   ? (int) $claims['max_users']   : 0,
                'max_tenants'    => isset($claims['max_tenants']) ? (int) $claims['max_tenants'] : 0,
                'features'       => $features,
                'issued_at'      => $issuedAt?->toIso8601String(),
                'expires_at'     => $expiresAt?->toIso8601String(),
                'days_left'      => $daysLeft,
                'is_perpetual'   => $expiresAt === null,
                'is_expired'     => $expiresAt && $expiresAt->isPast(),
                'last_heartbeat' => $lastHeartbeatIso,
            ],
            'usage' => [
                'user_count'   => $userCount,
                'tenant_count' => $tenantCount,
            ],
            'version' => [
                'current'   => $currentVersion,
                'latest'    => $latestVersion,
                'is_behind' => $latestVersion !== null && $currentVersion !== $latestVersion,
            ],
            'renewal' => [
                'contact_email' => config('deployment.vendor_contact_email')
                    ?? config('mail.from.address'),
                'contact_url'   => config('deployment.vendor_contact_url'),
                'vendor_name'   => config('deployment.vendor_name', 'BukuCloud'),
            ],
        ]);
    }
}

