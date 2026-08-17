<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class CopilotCreditPurchase extends Model
{
    use CentralConnection;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<string, array{credits: int, amount: float, label: string}> */
    public const PACKS = [
        'starter' => ['credits' => 50, 'amount' => 12.00, 'label' => '50 credits'],
        'standard' => ['credits' => 100, 'amount' => 22.00, 'label' => '100 credits'],
        'power' => ['credits' => 250, 'amount' => 49.00, 'label' => '250 credits'],
    ];

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'user_id',
        'pack',
        'credits',
        'amount',
        'currency',
        'status',
        'gateway',
        'gateway_bill_code',
        'paid_at',
        'failure_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'credits' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
