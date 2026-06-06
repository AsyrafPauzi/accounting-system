<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Server-side (publisher) record of a customer self-hosted install,
 * keyed by the license_id baked into their license key. One row per
 * licensed install. Gets updated on every heartbeat.
 */
class SelfHostedInstall extends Model
{
    use CentralConnection;

    protected $fillable = [
        'license_id',
        'customer_id',
        'customer_name',
        'plan_tier',
        'max_users',
        'max_tenants',
        'features',
        'expires_at',
        'issued_at',
        'latest_version',
        'latest_user_count',
        'latest_payload',
        'latest_ip',
        'latest_heartbeat_at',
        'first_heartbeat_at',
        'revoked_at',
        'revoked_reason',
    ];

    protected $casts = [
        'features'             => 'array',
        'latest_payload'       => 'array',
        'expires_at'           => 'datetime',
        'issued_at'            => 'datetime',
        'latest_heartbeat_at'  => 'datetime',
        'first_heartbeat_at'   => 'datetime',
        'revoked_at'           => 'datetime',
    ];

    public function heartbeats()
    {
        return $this->hasMany(SelfHostedHeartbeat::class, 'install_id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
