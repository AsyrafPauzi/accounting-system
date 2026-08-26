<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * An accountancy firm — the top of the Practice hierarchy. Owns one or
 * more `firm_clients` rows, each pointing at a regular tenant database.
 *
 * Firms always live on the central connection because they need to
 * reach across tenant boundaries (which is the whole point of the
 * Practice console). They never participate in tenant-DB queries.
 */
class Firm extends Model
{
    use HasFactory, SoftDeletes, CentralConnection;

    protected $fillable = [
        'name',
        'slug',
        'owner_user_id',
        'firm_subscription_id',
        'contact_email',
        'contact_phone',
        'country',
        'status',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function staff()
    {
        return $this->hasMany(User::class, 'firm_id');
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class, 'firm_subscription_id');
    }

    /**
     * Practice-level subscriptions live in the same `subscriptions`
     * table as tenant-level ones, distinguished by `firm_id` being set.
     * This relationship gives us "any firm subscription including
     * pending ones", which is the right shape for billing UIs.
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'firm_id');
    }

    public function clients()
    {
        return $this->hasMany(FirmClient::class, 'firm_id');
    }

    public function activeClients()
    {
        return $this->clients()->where('status', 'active');
    }

    public function invitations()
    {
        return $this->hasMany(FirmInvitation::class, 'firm_id');
    }

    /**
     * Convenience: a firm "is" active when both the firm row and its
     * billing subscription are in a usable state. We default to active
     * if the subscription pointer is null (mid-onboarding), so the
     * console doesn't lock the owner out before they finish checkout.
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        return $this->subscription?->isActive() ?? true;
    }

    /**
     * Maximum number of client tenants this firm can manage. The
     * source of truth depends on deployment shape:
     *
     *   - SaaS                     → the firm's Practice plan's
     *                                `client_cap` column.
     *   - Self-hosted (Enterprise) → the install's license claim
     *                                `max_tenants` (0 = unlimited).
     *
     * Returns null for "unlimited"; falls back to 1 for a SaaS firm
     * with no plan attached (mirrors Practice Free) so we never
     * accidentally treat "no plan" as "no cap".
     */
    public function clientCap(): ?int
    {
        if (\App\Support\Deployment::isSelfHosted()) {
            $cap = \App\Support\Deployment::licenseMaxTenants();
            // 0 / null in the license claim means "unlimited" by
            // convention; convert to null here so the rest of the
            // codebase's "null = unlimited" idiom keeps working.
            if ($cap === null || $cap === 0) {
                return null;
            }
            return $cap;
        }

        $plan = $this->subscription?->plan;
        if (! $plan) {
            return 1;
        }
        // null means unlimited; 0 should never appear but treat as
        // "no clients allowed" (forces upgrade) rather than 0=unlimited.
        return $plan->client_cap;
    }

    /** Currently linked active clients (excludes pending invites). */
    public function currentClientCount(): int
    {
        return $this->clients()->where('status', 'active')->count();
    }

    /** Returns null when unlimited, else the integer slots left. */
    public function clientsRemaining(): ?int
    {
        $cap = $this->clientCap();
        if ($cap === null) {
            return null;
        }
        return max(0, $cap - $this->currentClientCount());
    }

    /**
     * Can this firm add (or invite) one more client right now? We
     * count *pending* firm-initiated invites toward the cap so a firm
     * can't queue up 100 invites on a 1-client plan and bypass billing
     * by waiting for accepts.
     */
    public function canAddClient(): bool
    {
        $cap = $this->clientCap();
        if ($cap === null) {
            return true;
        }
        $pending = $this->invitations()
            ->where('direction', FirmInvitation::DIRECTION_FIRM_TO_CLIENT)
            ->where('status', FirmInvitation::STATUS_PENDING)
            ->count();
        return ($this->currentClientCount() + $pending) < $cap;
    }

    /** Firm-staff seats included on the Practice plan (+ paid extras). */
    public function staffSeatCap(): int
    {
        $sub = $this->subscription;
        if (! $sub) {
            return 1;
        }

        return $sub->totalSeats();
    }

    /** Active firm staff (owner + invited staff). */
    public function currentStaffCount(): int
    {
        return $this->staff()->whereNotNull('firm_role')->count();
    }

    public function canAddStaff(): bool
    {
        return $this->currentStaffCount() < $this->staffSeatCap();
    }
}
