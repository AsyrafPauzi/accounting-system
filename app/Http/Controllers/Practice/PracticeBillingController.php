<?php

namespace App\Http\Controllers\Practice;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\ToyyibpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plan picker for accountancy firms.
 *
 * Mirrors the SME SubscriptionController flow but operates on the
 * firm-level subscription (tenant_id is null, firm_id is the firm).
 * New firms land on Practice Free at signup; from here they upgrade
 * to a paid Practice plan when they're ready.
 */
class PracticeBillingController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user && $user->isFirmUser(), 403);
        $firmId = $user->firm_id;

        // Order: paid tiers ascending by monthly price (Free first since
        // its price is 0), then contact-sales tiers (Practice Self-hosted)
        // last. We deliberately don't fold the contact-sales rows in by
        // price — RM 0 is the seeder placeholder, not a real price, so
        // sorting them numerically would put Self-hosted next to Free.
        $plans = Plan::where('is_active', true)
            ->where('audience', 'practice')
            ->orderBy('is_contact_sales')
            ->orderBy('price_monthly')
            ->orderBy('id')
            ->get();

        $current = Subscription::where('firm_id', $firmId)
            ->whereNull('tenant_id')
            ->active()
            ->with(['plan', 'pendingPlan'])
            ->first();

        return Inertia::render('Practice/Plan', [
            'plans' => $plans,
            'currentSubscription' => $current,
        ]);
    }

    public function checkout(Request $request, ToyyibpayService $toyyibpay)
    {
        $user = $request->user();
        abort_unless($user && $user->isFirmOwner(), 403);
        $firmId = $user->firm_id;

        $validated = $request->validate([
            'plan_id'  => ['required', 'integer'],
            'interval' => ['required', 'in:monthly,yearly'],
        ]);

        $plan = Plan::where('is_active', true)
            ->where('audience', 'practice')
            ->findOrFail($validated['plan_id']);

        // Self-hosted (and any other contact-sales tier) is procured
        // out-of-band — the picker should be sending the firm to a
        // mailto, but defence in depth: the checkout endpoint refuses
        // to mint a Toyyibpay bill for a contact-sales plan.
        if ($plan->is_contact_sales) {
            return redirect()->route('practice.plan')->with(
                'error',
                "{$plan->name} is sold via our sales team. Email sales@bukucloud.com to get a custom quote."
            );
        }

        $current = Subscription::where('firm_id', $firmId)
            ->whereNull('tenant_id')
            ->active()
            ->with('plan')
            ->first();

        $billAmount = $plan->priceForInterval($validated['interval']);

        // No-op: same plan + same interval.
        if (
            $current
            && $current->plan_id === $plan->id
            && $current->interval === $validated['interval']
        ) {
            return redirect()->route('practice.plan')
                ->with('info', "You're already on this plan and billing cadence.");
        }

        // Downgrade — schedule for end of period (same logic as SME).
        if ($current && $this->isDowngrade($current, $plan)) {
            $current->update([
                'pending_plan_id'  => $plan->id,
                'pending_interval' => $validated['interval'],
            ]);

            $effective = optional($current->current_period_ends_at)->toDateString()
                ?? now()->addMonth()->toDateString();

            Log::info('Practice subscription downgrade scheduled', [
                'firm_id'          => $firmId,
                'current_plan'     => $current->plan?->slug,
                'pending_plan'     => $plan->slug,
                'pending_interval' => $validated['interval'],
                'effective_date'   => $effective,
            ]);

            return redirect()->route('practice.plan')->with(
                'success',
                "Downgrade scheduled. You'll keep {$current->plan->name} until {$effective}, then automatically switch to {$plan->name}."
            );
        }

        // Free plan → flip immediately, no payment.
        if ($billAmount <= 0) {
            Subscription::updateOrCreate(
                ['firm_id' => $firmId, 'tenant_id' => null],
                [
                    'plan_id'                => $plan->id,
                    'pending_plan_id'        => null,
                    'pending_interval'       => null,
                    'status'                 => 'active',
                    'interval'               => $validated['interval'],
                    'gateway'                => 'system',
                    'current_period_start'   => now()->toDateString(),
                    'current_period_ends_at' => now()->addMonth()->toDateString(),
                ]
            );

            return redirect()->route('practice.dashboard')
                ->with('success', 'You are now on the '.$plan->name.' plan.');
        }

        // Paid → paid upgrade. We park the target plan in the
        // pending_plan_id / pending_interval columns and start a Toyyibpay
        // bill against the existing subscription row. The current paid
        // plan keeps the firm running until the webhook flips
        // `pending_*` into the active fields. This mirrors how a SaaS
        // billing system would proration-charge an upgrade — except
        // Toyyibpay only does one-shot bills, so we approximate by
        // restarting the period at payment time. (For a downgrade we
        // preserve the period via the branch above.)
        if ($current && $current->plan && $current->plan->slug !== 'practice-free') {
            $current->update([
                'pending_plan_id'  => $plan->id,
                'pending_interval' => $validated['interval'],
                // Status stays 'active' — losing access during checkout
                // would be hostile. The webhook swaps plan_id atomically.
                'gateway'          => 'toyyibpay',
            ]);

            Log::info('Initiating practice subscription upgrade', [
                'firm_id'           => $firmId,
                'current_plan'      => $current->plan->slug,
                'target_plan'       => $plan->slug,
                'pending_interval'  => $validated['interval'],
                'amount'            => $billAmount,
            ]);

            $paymentUrl = $toyyibpay->createBill([
                'billName'                => "Practice plan upgrade: {$plan->name}",
                'billDescription'         => "Upgrade to {$plan->name} ({$validated['interval']}) for firm #{$firmId}",
                'billAmount'              => $billAmount,
                'billReturnUrl'           => route('subscription.callback'),
                'billCallbackUrl'         => route('subscription.webhook'),
                'billExternalReferenceNo' => (string) $current->id,
                'billTo'                  => $user->name,
                'billEmail'               => $user->email,
                'billPhone'               => $user->phone ?? '0123456789',
            ]);

            if (! $paymentUrl) {
                // Roll the pending fields back so the user can retry
                // without phantom state lingering on the subscription.
                $current->update([
                    'pending_plan_id'  => null,
                    'pending_interval' => null,
                ]);
                return redirect()->back()->with(
                    'error',
                    'Failed to initialise payment. Please try again later.'
                );
            }

            return Inertia::location($paymentUrl);
        }

        // First paid Practice subscription (or coming from Practice Free) →
        // Toyyibpay checkout.
        $subscription = Subscription::updateOrCreate(
            ['firm_id' => $firmId, 'tenant_id' => null],
            [
                'plan_id'          => $plan->id,
                'pending_plan_id'  => null,
                'pending_interval' => null,
                'status'           => 'pending',
                'interval'         => $validated['interval'],
                'gateway'          => 'toyyibpay',
            ]
        );

        Log::info('Initiating practice subscription checkout', [
            'firm_id'  => $firmId,
            'plan'     => $plan->name,
            'interval' => $validated['interval'],
            'amount'   => $billAmount,
        ]);

        $paymentUrl = $toyyibpay->createBill([
            'billName'                => "Practice plan: {$plan->name}",
            'billDescription'         => "{$plan->name} ({$validated['interval']}) for firm #{$firmId}",
            'billAmount'              => $billAmount,
            'billReturnUrl'           => route('subscription.callback'),
            'billCallbackUrl'         => route('subscription.webhook'),
            'billExternalReferenceNo' => (string) $subscription->id,
            'billTo'                  => $user->name,
            'billEmail'               => $user->email,
            'billPhone'               => $user->phone ?? '0123456789',
        ]);

        if (! $paymentUrl) {
            return redirect()->back()->with('error', 'Failed to initialise payment. Please try again later.');
        }

        return Inertia::location($paymentUrl);
    }

    private function isDowngrade(Subscription $current, Plan $target): bool
    {
        // From Practice Free → not a downgrade.
        if (! $current->plan || $current->plan->slug === 'practice-free') {
            return false;
        }
        // To Practice Free from any paid → always downgrade.
        if ($target->slug === 'practice-free') {
            return true;
        }

        return (float) $target->price_monthly < (float) $current->plan->price_monthly;
    }
}
