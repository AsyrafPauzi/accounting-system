<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Subscription extends Model
{
    use CentralConnection, Auditable;

    protected $fillable = [
        'tenant_id',
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['active', 'trialing'])
            ->where(function ($q) {
                $q->whereNull('current_period_ends_at')
                  ->orWhereDate('current_period_ends_at', '>=', now()->toDateString());
            });
    }

    public function isActive(): bool
    {
        if (! in_array($this->status, ['active', 'trialing'], true)) {
            return false;
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

