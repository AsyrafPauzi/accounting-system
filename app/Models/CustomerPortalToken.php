<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPortalToken extends Model
{
    protected $fillable = [
        'customer_id',
        'token',
        'expires_at',
        'last_used_at',
        'created_by',
    ];

    protected $casts = [
        'expires_at'   => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isValid(): bool
    {
        return $this->expires_at->isFuture();
    }
}
