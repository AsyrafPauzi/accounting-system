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
        'users_included',
        'extra_user_price',
        'features',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
        'extra_user_price' => 'decimal:2',
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

