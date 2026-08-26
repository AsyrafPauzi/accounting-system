<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Support\SubscriptionPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Subscription extends Model
{
    use CentralConnection, Auditable, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'firm_id',
        'plan_id',
        'pending_plan_id',
        'pending_interval',
        'status',
        'interval',
        'current_period_start',
        'current_period_ends_at',
        'gateway',
        'gateway_subscription_id',
        'extra_seats',
    ];

    protected $casts = [
        'current_period_start' => 'date',
        'current_period_ends_at' => 'date',
        'extra_seats' => 'integer',
    ];

    protected $appends = ['has_pending_change'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Practice-level subscriptions belong to a firm rather than a
     * tenant. Both relationships exist on the same row so the billing
     * pipeline doesn't have to special-case where the row came from.
     */
    public function firm()
    {
        return $this->belongsTo(Firm::class, 'firm_id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Plan the tenant has scheduled to switch to once the current period
     * ends. Null when there's no pending change.
     */
    public function pendingPlan()
    {
        return $this->belongsTo(Plan::class, 'pending_plan_id');
    }

    public function getHasPendingChangeAttribute(): bool
    {
        return ! empty($this->pending_plan_id);
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(SubscriptionRenewal::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        // past_due rows stay in the active set until subscription:expire
        // flips them to expired; isActive() still enforces the grace window.
        return $query->whereIn('status', ['active', 'trialing', 'past_due'])
            ->where(function ($q) {
                $q->whereNull('current_period_ends_at')
                    ->orWhereDate('current_period_ends_at', '>=', now()->toDateString())
                    ->orWhere('status', 'past_due');
            });
    }

    public function isActive(): bool
    {
        if (! in_array($this->status, ['active', 'trialing', 'past_due'], true)) {
            return false;
        }

        if ($this->status === 'past_due') {
            $ends = $this->current_period_ends_at?->toDateString();
            if (! $ends) {
                return true;
            }

            return SubscriptionPeriod::graceDeadline($ends) >= now()->toDateString();
        }

        if ($this->current_period_ends_at === null) {
            return true;
        }

        return $this->current_period_ends_at->isFuture() || $this->current_period_ends_at->isToday();
    }

    /**
     * Total seats this subscription is entitled to right now.
     *
     * = plan's included seats + paid extras (each extra has its own purchase
     *   row in `extra_seat_purchases` with status = paid, and bumps this
     *   counter on the way through).
     */
    public function totalSeats(): int
    {
        $included = (int) ($this->plan?->users_included ?? 1);
        return $included + (int) ($this->extra_seats ?? 0);
    }
}

