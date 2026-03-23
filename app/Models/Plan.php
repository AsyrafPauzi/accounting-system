<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Plan extends Model
{
    use CentralConnection;

    protected $fillable = [
        'name',
        'slug',
        'price_monthly',
        'price_yearly',
        'features',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function priceForInterval(string $interval): float
    {
        return (float) ($interval === 'yearly' ? $this->price_yearly : $this->price_monthly);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}

