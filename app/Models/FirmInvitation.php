<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Pending invitations that connect a Firm and a Tenant. Two directions:
 *
 *   firm_invites_client
 *     A firm wants to onboard a client. The firm enters the client's
 *     email; we email a signed link; the client either creates a tenant
 *     during the accept flow or links the invite to an existing tenant
 *     they own.
 *
 *   client_invites_firm
 *     A self-managed tenant wants their accountant to take over. The
 *     tenant admin enters the firm's email; the firm accepts from
 *     inside the Practice console.
 *
 * Tokens are 60-character random strings, indexed unique. We could have
 * used a JWT, but plain random tokens are easier to reason about and
 * we already gate them with explicit DB lookups + status + expiry.
 */
class FirmInvitation extends Model
{
    use CentralConnection;

    public const DIRECTION_FIRM_TO_CLIENT = 'firm_invites_client';
    public const DIRECTION_CLIENT_TO_FIRM = 'client_invites_firm';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REVOKED  = 'revoked';
    public const STATUS_EXPIRED  = 'expired';

    /** Default lifetime — long enough for a real human to find the email. */
    public const DEFAULT_TTL_DAYS = 14;

    protected $fillable = [
        'firm_id',
        'tenant_id',
        'direction',
        'email',
        'token',
        'permission_level',
        'status',
        'expires_at',
        'accepted_at',
        'accepted_by_user_id',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function firm()
    {
        return $this->belongsTo(Firm::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function acceptedBy()
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public static function generateToken(): string
    {
        return Str::random(60);
    }

    public static function defaultExpiresAt(): Carbon
    {
        return Carbon::now()->addDays(self::DEFAULT_TTL_DAYS);
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
