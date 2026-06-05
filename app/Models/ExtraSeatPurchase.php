<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Represents a tenant's intent to add a paid extra seat to their team.
 *
 * Lifecycle:
 *   pending  → row created when the admin clicks "Add user" and is over the
 *              included-seat limit. Password is already hashed.
 *   paid     → Toyyibpay webhook confirmed the payment. The corresponding
 *              User row is created and `subscriptions.extra_seats` is bumped.
 *   failed   → gateway returned non-success. No user is created.
 *   cancelled → admin abandoned the payment (best-effort, optional).
 *
 * Why a separate table?
 *   The previous implementation passed the desired user's plaintext password
 *   through `billExternalReferenceNo` so the webhook could create the user
 *   afterwards. That payload ends up in Toyyibpay's logs and our own request
 *   logs. Holding the draft here lets us pass only the purchase id to the
 *   gateway, while the password hash never leaves our database.
 */
class ExtraSeatPurchase extends Model
{
    use CentralConnection;

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'name',
        'email',
        'password_hash',
        'role',
        'amount',
        'currency',
        'status',
        'gateway',
        'gateway_bill_code',
        'user_id',
        'paid_at',
        'failure_reason',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /** Default lifecycle is "draft" — only created rows that mean something. */
    public const STATUS_PENDING   = 'pending';
    public const STATUS_PAID      = 'paid';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
