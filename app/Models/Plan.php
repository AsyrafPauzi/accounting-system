<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasPermissions;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Plan extends Model
{
    use CentralConnection, HasPermissions, Auditable;

    protected $guard_name = 'web';

    protected $fillable = [
        'name',
        'slug',
        'audience',
        'price_monthly',
        'price_yearly',
        'users_included',
        'client_cap',
        'extra_user_price',
        'copilot_credits_monthly',
        'features',
        'is_active',
        'is_contact_sales',
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
        'is_contact_sales' => 'boolean',
        'extra_user_price' => 'decimal:2',
        'copilot_credits_monthly' => 'integer',
        // Stored unsigned int but null means "unlimited"; cast keeps
        // the contract explicit at every read site.
        'client_cap' => 'integer',
    ];

    public function priceForInterval(string $interval): float
    {
        return (float) ($interval === 'yearly' ? $this->price_yearly : $this->price_monthly);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->permissions()->where('name', $permission)->exists();
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}

