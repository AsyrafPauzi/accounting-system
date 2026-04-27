<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Subscription extends Model
{
    use CentralConnection;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'interval',
        'current_period_start',
        'current_period_ends_at',
        'gateway',
        'gateway_subscription_id',
    ];

    protected $casts = [
        'current_period_start' => 'date',
        'current_period_ends_at' => 'date',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
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
}

