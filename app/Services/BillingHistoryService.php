<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ExtraSeatPurchase;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Translates the raw `audit_logs` rows attached to a Subscription (and
 * its sibling ExtraSeatPurchase rows) into a human-readable billing
 * timeline that the SME `Settings/Plan` and firm `Settings/PlanFirm`
 * pages render at the bottom of their plan card.
 *
 * Why a service and not just a query in the controller?
 *
 *   The audit log stores raw column-level dirty state. Mapping
 *   "plan_id changed from 4 to 7 + status changed from pending to active"
 *   into a single user-facing event ("Upgraded to Growth") needs the
 *   Plan name lookup, the actor resolution, and the noise filter (we
 *   suppress updates that ONLY moved bookkeeping fields like
 *   gateway_subscription_id without a user-visible side-effect). That
 *   logic doesn't belong on the controller.
 *
 *   We also merge ExtraSeatPurchase rows so "added a 4th seat" shows
 *   in the same timeline as "renewed for another month" — from the
 *   tenant's perspective they're both billing events.
 *
 * Output shape (stable contract for the React component):
 *
 *   [
 *     {
 *       "id": "audit-1234"   // stable per-row key for React
 *       "happened_at": "2026-06-09T17:30:00+08:00",
 *       "type": "plan_changed",
 *       "icon": "arrow-up",
 *       "title": "Upgraded to Practice Growth",
 *       "detail": "Yearly subscription. Renews on 12 Sep 2026.",
 *       "actor": "Asyraf Pauzi"   // null when system / webhook / null user
 *     },
 *     ...
 *   ]
 */
class BillingHistoryService
{
    /** Hard cap so a tenant with thousands of audit rows can't blow the page. */
    private const MAX_EVENTS = 50;

    /**
     * Build the timeline for the given subscription. Pass null safely —
     * the service returns an empty array, which the UI renders as an
     * empty-state card instead of throwing.
     */
    public function forSubscription(?Subscription $subscription): array
    {
        if (! $subscription) {
            return [];
        }

        $audits = AuditLog::query()
            ->where('auditable_type', Subscription::class)
            ->where('auditable_id', $subscription->id)
            ->orderByDesc('id')
            ->limit(self::MAX_EVENTS * 2) // pull extras; we filter noise after
            ->get();

        // Pre-fetch every plan id we'll need to name. The audit rows
        // store plan_id as integers; we don't want N+1 lookups while
        // formatting the timeline.
        $planIds = $audits->flatMap(function (AuditLog $log) {
            return [
                $log->old_values['plan_id'] ?? null,
                $log->new_values['plan_id'] ?? null,
                $log->old_values['pending_plan_id'] ?? null,
                $log->new_values['pending_plan_id'] ?? null,
            ];
        })->filter()->unique()->values();

        $plans = $planIds->isEmpty()
            ? collect()
            : Plan::query()->whereIn('id', $planIds)->get()->keyBy('id');

        $events = $audits
            ->map(fn (AuditLog $log) => $this->mapAudit($log, $plans))
            ->filter() // drop noise rows mapAudit returned null for
            ->values();

        // Extra-seat purchases are scoped per tenant (firm-side never has
        // these — we already gate seat purchases to SME admins). Joining
        // on subscription_id keeps the query trivially indexed.
        if ($subscription->tenant_id) {
            $seatEvents = $this->extraSeatEvents($subscription);
            $events = $events->merge($seatEvents)->values();
        }

        return $events
            ->sortByDesc('happened_at')
            ->take(self::MAX_EVENTS)
            ->values()
            ->all();
    }

    private function mapAudit(AuditLog $log, Collection $plans): ?array
    {
        $old = $log->old_values ?? [];
        $new = $log->new_values ?? [];
        // `updated_at` ticks on every write and is never user-meaningful
        // on its own. Strip it up front so it never trips the generic
        // fallback or confuses the noise filter below.
        unset($old['updated_at'], $new['updated_at']);

        $when = $log->created_at instanceof Carbon
            ? $log->created_at
            : Carbon::parse((string) $log->created_at);
        $actor = $log->user_name ?: null;
        $idKey = "audit-{$log->id}";

        if ($log->event === 'created') {
            $planId = $new['plan_id'] ?? null;
            $plan = $planId ? $plans->get($planId) : null;
            $interval = $new['interval'] ?? null;

            // Trial-on-signup is a `created` event with status="trialing".
            // Render it as a distinct "Started 14-day trial" entry instead
            // of generic "Subscribed to Corporate" so the timeline reads
            // honestly when the auto-downgrade-to-Startup fires later.
            if (($new['status'] ?? null) === 'trialing') {
                $endsAt = $new['current_period_ends_at'] ?? null;
                $detail = $endsAt
                    ? 'Auto-switches to the free plan on '.$this->formatDate($endsAt).' unless you upgrade.'
                    : 'Auto-switches to the free plan when the trial ends.';
                return [
                    'id' => $idKey,
                    'happened_at' => $when->toIso8601String(),
                    'type' => 'trial_started',
                    'icon' => 'sparkles',
                    'title' => $plan
                        ? "Started trial of {$plan->name}"
                        : 'Started subscription trial',
                    'detail' => $detail,
                    'actor' => $actor,
                ];
            }

            return [
                'id' => $idKey,
                'happened_at' => $when->toIso8601String(),
                'type' => 'subscribed',
                'icon' => 'sparkles',
                'title' => $plan
                    ? 'Subscribed to '.$plan->name
                    : 'Subscription created',
                'detail' => $this->intervalLabel($interval, $new['current_period_ends_at'] ?? null, $new['gateway'] ?? null),
                'actor' => $actor,
            ];
        }

        if ($log->event === 'deleted' || $log->event === 'soft_deleted') {
            return [
                'id' => $idKey,
                'happened_at' => $when->toIso8601String(),
                'type' => 'deleted',
                'icon' => 'trash',
                'title' => 'Subscription removed',
                'detail' => null,
                'actor' => $actor,
            ];
        }

        if ($log->event !== 'updated') {
            return null;
        }

        // Plan switch — the headline event. We always show it even if
        // status / interval also changed in the same write because the
        // user cares about WHICH plan they're on more than anything.
        if (array_key_exists('plan_id', $new)) {
            $oldPlan = isset($old['plan_id']) ? $plans->get($old['plan_id']) : null;
            $newPlan = $plans->get($new['plan_id']);
            $oldName = $oldPlan?->name ?? '—';
            $newName = $newPlan?->name ?? '—';
            $oldPrice = (float) ($oldPlan?->price_monthly ?? 0);
            $newPrice = (float) ($newPlan?->price_monthly ?? 0);
            $isUpgrade = $newPrice > $oldPrice;

            // Special case: ApplyPendingSubscriptionChanges flipping a
            // `trialing` row to a free plan IS the trial expiring. The
            // single audit row has plan_id, status, gateway, and the
            // period dirty all at once, but the user-visible event is
            // "your trial ended" — not "you downgraded". Detect it here
            // and emit a dedicated `trial_expired` so the timeline reads
            // correctly instead of saying "Downgraded to Startup (Free)"
            // for what was an automatic, non-user-initiated switch.
            $oldStatus = $old['status'] ?? null;
            if ($oldStatus === 'trialing' && $newPrice === 0.0) {
                return [
                    'id' => $idKey,
                    'happened_at' => $when->toIso8601String(),
                    'type' => 'trial_expired',
                    'icon' => 'clock',
                    'title' => "Trial ended — moved to {$newName} (Free)",
                    'detail' => "Your free trial of {$oldName} finished. Upgrade any time to restore the paid features.",
                    'actor' => null, // system action, never a real user
                ];
            }

            // Other transition out of trialing → user paid mid-trial.
            // Render as "Trial converted" instead of generic Upgrade so
            // the history captures intent.
            if ($oldStatus === 'trialing' && $newPrice > 0.0) {
                return [
                    'id' => $idKey,
                    'happened_at' => $when->toIso8601String(),
                    'type' => 'trial_converted',
                    'icon' => 'arrow-up',
                    'title' => "Trial converted to {$newName}",
                    'detail' => "Trial of {$oldName} ended early — paid plan is now active.".$this->intervalSuffix($new['interval'] ?? null, $new['current_period_ends_at'] ?? null),
                    'actor' => $actor,
                ];
            }

            return [
                'id' => $idKey,
                'happened_at' => $when->toIso8601String(),
                'type' => 'plan_changed',
                'icon' => $isUpgrade ? 'arrow-up' : 'arrow-down',
                'title' => $isUpgrade
                    ? "Upgraded to {$newName}"
                    : ($newPrice === 0.0 ? "Downgraded to {$newName} (Free)" : "Switched to {$newName}"),
                'detail' => "{$oldName} → {$newName}".$this->intervalSuffix($new['interval'] ?? null, $new['current_period_ends_at'] ?? null),
                'actor' => $actor,
            ];
        }

        // Status flips (cancel / reactivate / past_due / expired).
        if (array_key_exists('status', $new)) {
            return $this->mapStatusChange($idKey, $when, $old, $new, $actor);
        }

        // Pending change scheduled / cancelled. Important UX: tenants
        // *expect* to see "you scheduled a downgrade" in their history
        // even when the actual cutover hasn't happened yet.
        if (array_key_exists('pending_plan_id', $new)) {
            return $this->mapPendingChange($idKey, $when, $old, $new, $plans, $actor);
        }

        // Renewal — period boundaries moved forward without a plan
        // change. Toyyibpay webhooks hit this path for the
        // recurring-renewal scenario, where both `current_period_start`
        // and `current_period_ends_at` get rewritten in the same write.
        if (array_key_exists('current_period_ends_at', $new) || array_key_exists('current_period_start', $new)) {
            $newEnd = $new['current_period_ends_at'] ?? null;
            $newStart = $new['current_period_start'] ?? null;
            if ($newEnd) {
                $detail = 'Now valid until '.$this->formatDate($newEnd).'.';
            } elseif ($newStart) {
                $detail = 'New billing period started '.$this->formatDate($newStart).'.';
            } else {
                $detail = null;
            }
            return [
                'id' => $idKey,
                'happened_at' => $when->toIso8601String(),
                'type' => 'renewed',
                'icon' => 'refresh',
                'title' => 'Subscription renewed',
                'detail' => $detail,
                'actor' => $actor,
            ];
        }

        // Extra seats counter went up (firm or SME). Detail rendering
        // happens in extraSeatEvents(); here we suppress to avoid double-
        // counting the same purchase.
        if (array_key_exists('extra_seats', $new)) {
            return null;
        }

        // Bookkeeping-only writes (gateway_subscription_id stamp, etc.)
        // are noise — don't pollute the timeline. We've already
        // unset `updated_at` at the top of this method.
        $bookkeepingOnly = array_diff_key(
            $new,
            array_flip(['gateway_subscription_id', 'gateway'])
        );
        if (empty($bookkeepingOnly)) {
            return null;
        }

        // Generic fallback so we never silently drop a write the user
        // might be asking us about. Keeps support debugging trivial.
        return [
            'id' => $idKey,
            'happened_at' => $when->toIso8601String(),
            'type' => 'updated',
            'icon' => 'pencil',
            'title' => 'Subscription updated',
            'detail' => $this->summariseGenericChange($old, $new),
            'actor' => $actor,
        ];
    }

    private function mapStatusChange(string $idKey, Carbon $when, array $old, array $new, ?string $actor): array
    {
        $oldStatus = $old['status'] ?? null;
        $newStatus = $new['status'] ?? null;

        // active is the goal state. Coming from anywhere else = a
        // reactivation (post-payment, manual admin reactivation, etc.)
        // and that's worth its own event for the audit trail.
        if ($newStatus === 'active' && $oldStatus !== 'active') {
            return [
                'id' => $idKey,
                'happened_at' => $when->toIso8601String(),
                'type' => 'reactivated',
                'icon' => 'check',
                'title' => $oldStatus === 'pending' ? 'Payment confirmed' : 'Subscription reactivated',
                'detail' => $oldStatus
                    ? "Status changed from {$oldStatus} → active."
                    : null,
                'actor' => $actor,
            ];
        }

        // Active-to-not. The most painful event in this entire timeline,
        // from a customer-trust standpoint — they should see exactly
        // when and why.
        $titles = [
            'canceled'  => 'Subscription canceled',
            'cancelled' => 'Subscription cancelled', // tolerate either spelling
            'expired'   => 'Subscription expired',
            'past_due'  => 'Payment failed (past due)',
            'pending'   => 'Awaiting payment',
        ];
        $icons = [
            'canceled'  => 'x',
            'cancelled' => 'x',
            'expired'   => 'clock',
            'past_due'  => 'alert',
            'pending'   => 'clock',
        ];
        $title = $titles[$newStatus] ?? "Status changed to {$newStatus}";
        $icon = $icons[$newStatus] ?? 'pencil';

        return [
            'id' => $idKey,
            'happened_at' => $when->toIso8601String(),
            'type' => 'status_changed',
            'icon' => $icon,
            'title' => $title,
            'detail' => $oldStatus ? "Was: {$oldStatus}" : null,
            'actor' => $actor,
        ];
    }

    private function mapPendingChange(string $idKey, Carbon $when, array $old, array $new, Collection $plans, ?string $actor): ?array
    {
        $oldPending = $old['pending_plan_id'] ?? null;
        $newPending = $new['pending_plan_id'] ?? null;

        if ($newPending) {
            $plan = $plans->get($newPending);
            $interval = $new['pending_interval'] ?? null;
            return [
                'id' => $idKey,
                'happened_at' => $when->toIso8601String(),
                'type' => 'change_scheduled',
                'icon' => 'calendar',
                'title' => $plan
                    ? "Scheduled change to {$plan->name}"
                    : 'Plan change scheduled',
                'detail' => $interval
                    ? "Switch to {$interval} billing on next renewal."
                    : 'Will apply on the next renewal date.',
                'actor' => $actor,
            ];
        }

        // Pending was cleared without a plan_id change → user cancelled
        // their scheduled change. (When the change actually applies, the
        // plan_id branch above fires first and we return there.)
        if ($oldPending) {
            $plan = $plans->get($oldPending);
            return [
                'id' => $idKey,
                'happened_at' => $when->toIso8601String(),
                'type' => 'change_cancelled',
                'icon' => 'undo',
                'title' => 'Scheduled plan change cancelled',
                'detail' => $plan
                    ? "{$plan->name} switch was cancelled — staying on the current plan."
                    : null,
                'actor' => $actor,
            ];
        }

        return null;
    }

    /**
     * Build extra-seat events from the dedicated table. We use the
     * canonical purchase rows here rather than the audit_log entries
     * on `subscriptions.extra_seats++` because the purchase row carries
     * the seat-holder's email and gateway code, which are the actually-
     * useful pieces of information on a billing-history page.
     */
    private function extraSeatEvents(Subscription $subscription): Collection
    {
        $rows = ExtraSeatPurchase::query()
            ->where('subscription_id', $subscription->id)
            ->orderByDesc('id')
            ->limit(self::MAX_EVENTS)
            ->get();

        return $rows->map(function (ExtraSeatPurchase $row) {
            $when = $row->paid_at ?: $row->created_at;
            return match ($row->status) {
                ExtraSeatPurchase::STATUS_PAID => [
                    'id' => "seat-{$row->id}",
                    'happened_at' => $when?->toIso8601String() ?? now()->toIso8601String(),
                    'type' => 'extra_seat_added',
                    'icon' => 'user-plus',
                    'title' => "Added a paid seat for {$row->email}",
                    'detail' => $row->amount
                        ? 'RM '.number_format((float) $row->amount, 2).' via '.($row->gateway ?: 'Toyyibpay').'.'
                        : 'Seat granted.',
                    'actor' => null,
                ],
                ExtraSeatPurchase::STATUS_FAILED => [
                    'id' => "seat-{$row->id}",
                    'happened_at' => $when?->toIso8601String() ?? now()->toIso8601String(),
                    'type' => 'extra_seat_failed',
                    'icon' => 'alert',
                    'title' => "Extra seat purchase failed ({$row->email})",
                    'detail' => $row->failure_reason,
                    'actor' => null,
                ],
                default => null,
            };
        })->filter();
    }

    private function intervalLabel(?string $interval, ?string $endsAt, ?string $gateway): ?string
    {
        if (! $interval) {
            return null;
        }
        if ($interval === 'lifetime') {
            return 'Lifetime — no expiry.';
        }
        $cadence = $interval === 'yearly' ? 'Yearly' : 'Monthly';
        if (! $endsAt) {
            return "{$cadence} billing.";
        }
        $verb = $gateway === 'system' ? 'Expires' : 'Renews';
        return "{$cadence} billing. {$verb} on ".$this->formatDate($endsAt).'.';
    }

    private function intervalSuffix(?string $interval, ?string $endsAt): string
    {
        if (! $interval) return '';
        if ($interval === 'lifetime') return ' · Lifetime.';
        $cadence = $interval === 'yearly' ? 'yearly' : 'monthly';
        if (! $endsAt) return " · {$cadence} billing.";
        return " · {$cadence}, renews ".$this->formatDate($endsAt).'.';
    }

    private function formatDate(string $iso): string
    {
        try {
            return Carbon::parse($iso)->format('j M Y');
        } catch (\Throwable) {
            return $iso;
        }
    }

    /**
     * Last-resort summariser for column changes we don't have a
     * dedicated translator for. Keeps the timeline informative without
     * leaking schema details to the customer.
     */
    private function summariseGenericChange(array $old, array $new): ?string
    {
        $labels = [
            'interval'         => 'billing cadence',
            'extra_seats'      => 'extra seats',
            'gateway'          => 'payment gateway',
        ];
        $parts = [];
        foreach ($new as $field => $value) {
            $label = $labels[$field] ?? $field;
            $oldValue = $old[$field] ?? '—';
            $parts[] = "{$label}: {$oldValue} → {$value}";
        }
        return $parts ? implode('; ', $parts) : null;
    }
}
