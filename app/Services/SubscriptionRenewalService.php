<?php

namespace App\Services;

use App\Mail\SubscriptionRenewalDue;
use App\Models\Subscription;
use App\Models\SubscriptionRenewal;
use App\Models\User;
use App\Support\Deployment;
use App\Support\SubscriptionPeriod;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SubscriptionRenewalService
{
    /**
     * Create (or return existing) pending Billplz renewal when due.
     * Returns the renewal when ensured; null when skipped.
     * Sets $created = true only when a new Billplz bill was created.
     */
    public function issueIfDue(Subscription $subscription, bool &$created = false): ?SubscriptionRenewal
    {
        $created = false;

        if (Deployment::isSelfHosted()) {
            return null;
        }

        $interval = $subscription->pending_interval ?: $subscription->interval;
        if (! in_array($interval, ['monthly', 'yearly'], true)) {
            return null;
        }

        $plan = $subscription->pendingPlan ?: $subscription->plan;
        if (! $plan) {
            $subscription->loadMissing(['plan', 'pendingPlan']);
            $plan = $subscription->pendingPlan ?: $subscription->plan;
        }
        if (! $plan) {
            return null;
        }

        $amount = $plan->priceForInterval($interval);
        if ($amount <= 0) {
            return null;
        }

        $existing = SubscriptionRenewal::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', 'pending')
            ->first();
        if ($existing) {
            return $existing;
        }

        $periodEnd = $subscription->current_period_ends_at?->toDateString();
        $lead = (int) config('subscriptions.renewal_lead_days', 7);
        if (! SubscriptionPeriod::isDue($periodEnd, $lead, now()->toDateString())) {
            return null;
        }

        $billplz = BillplzService::forPlatform();
        if (! $billplz) {
            Log::warning('subscription renewal skipped: platform Billplz not configured', [
                'subscription_id' => $subscription->id,
            ]);

            return null;
        }

        $window = SubscriptionPeriod::nextWindow($interval, $periodEnd);
        $email = $this->billingEmail($subscription);
        $name = $this->billingName($subscription);

        $bill = $billplz->createBillDetailed([
            'description' => "BukuCloud {$plan->name} ({$interval}) renewal #{$subscription->id}",
            'email' => $email ?: 'noreply@bukucloud.com',
            'name' => $name ?: 'Subscriber',
            'amount' => $amount,
            'callback_url' => route('subscription.webhook.billplz'),
            'redirect_url' => route('subscription.success'),
            'reference' => 'sub-renewal-'.$subscription->id,
        ]);

        if (! $bill) {
            Log::error('subscription renewal Billplz create failed', [
                'subscription_id' => $subscription->id,
            ]);

            return null;
        }

        $renewal = SubscriptionRenewal::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'interval' => $interval,
            'amount' => $amount,
            'status' => 'pending',
            'gateway' => 'billplz',
            'gateway_bill_id' => $bill['id'],
            'payment_url' => $bill['url'],
            'period_start' => $window['period_start'],
            'period_end' => $window['period_end'],
            'due_at' => $periodEnd,
        ]);

        $created = true;

        if ($email) {
            try {
                Mail::to($email)->send(new SubscriptionRenewalDue($renewal->load('plan')));
            } catch (\Throwable $e) {
                Log::warning('subscription renewal mail failed', [
                    'renewal_id' => $renewal->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $renewal;
    }

    public function markPaid(SubscriptionRenewal $renewal): void
    {
        if ($renewal->status === 'paid') {
            return;
        }

        $subscription = $renewal->subscription;
        if (! $subscription) {
            return;
        }

        $renewal->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $subscription->update([
            'status' => 'active',
            'plan_id' => $renewal->plan_id,
            'pending_plan_id' => null,
            'pending_interval' => null,
            'interval' => $renewal->interval,
            'current_period_start' => $renewal->period_start->toDateString(),
            'current_period_ends_at' => $renewal->period_end->toDateString(),
            'gateway' => 'billplz',
            'gateway_subscription_id' => $renewal->gateway_bill_id,
        ]);

        Log::info('Subscription renewed via Billplz', [
            'subscription_id' => $subscription->id,
            'renewal_id' => $renewal->id,
            'period_end' => $renewal->period_end->toDateString(),
        ]);
    }

    private function billingEmail(Subscription $subscription): ?string
    {
        if ($subscription->tenant_id) {
            $admin = User::query()
                ->where('tenant_id', $subscription->tenant_id)
                ->orderBy('id')
                ->first();

            return $admin?->email;
        }

        if ($subscription->firm_id) {
            $owner = User::query()
                ->where('firm_id', $subscription->firm_id)
                ->where('firm_role', 'owner')
                ->orderBy('id')
                ->first();

            return $owner?->email;
        }

        return null;
    }

    private function billingName(Subscription $subscription): ?string
    {
        if ($subscription->tenant_id) {
            return User::query()
                ->where('tenant_id', $subscription->tenant_id)
                ->orderBy('id')
                ->value('name');
        }

        if ($subscription->firm_id) {
            return User::query()
                ->where('firm_id', $subscription->firm_id)
                ->where('firm_role', 'owner')
                ->orderBy('id')
                ->value('name');
        }

        return null;
    }
}
