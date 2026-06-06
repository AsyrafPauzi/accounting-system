<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Append-only history of customer heartbeats. Useful for support
 * forensics ("when did this customer last check in?") and churn
 * analysis. Held on the publisher / SaaS DB only.
 */
class SelfHostedHeartbeat extends Model
{
    use CentralConnection;

    public $timestamps = false;

    protected $fillable = [
        'install_id',
        'version',
        'user_count',
        'payload',
        'ip',
        'received_at',
    ];

    protected $casts = [
        'payload'     => 'array',
        'received_at' => 'datetime',
    ];

    public function install()
    {
        return $this->belongsTo(SelfHostedInstall::class, 'install_id');
    }
}
