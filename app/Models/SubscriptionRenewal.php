<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class SubscriptionRenewal extends Model
{
    use CentralConnection, Auditable;

    protected $fillable = [
        'subscription_id',
        'plan_id',
        'interval',
        'amount',
        'status',
        'gateway',
        'gateway_bill_id',
        'payment_url',
        'period_start',
        'period_end',
        'due_at',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'due_at' => 'date',
        'paid_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
