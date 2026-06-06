<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Pivot between Firm and Tenant — represents "this firm manages this
 * client tenant's books". One row per (firm, tenant) pair, and the
 * tenant_id is uniquely indexed so a tenant can only be under one firm
 * at a time (otherwise practice billing math + access decisions get
 * ambiguous).
 *
 * Lifecycle:
 *   - 'active'  — both sides confirmed; firm has working access
 *   - 'pending' — invite sent / not yet accepted (used when we want a
 *                 placeholder row before final acceptance, e.g. for
 *                 firm-side projections)
 *   - 'paused'  — explicitly disabled by the firm or client owner;
 *                 firm staff lose access until reactivated
 */
class FirmClient extends Model
{
    use CentralConnection;

    protected $fillable = [
        'firm_id',
        'tenant_id',
        'permission_level',
        'status',
        'linked_at',
        'linked_by_user_id',
    ];

    protected $casts = [
        'linked_at' => 'datetime',
    ];

    public function firm()
    {
        return $this->belongsTo(Firm::class, 'firm_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function linkedBy()
    {
        return $this->belongsTo(User::class, 'linked_by_user_id');
    }
}
