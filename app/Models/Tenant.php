<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, CentralConnection;

    // This makes sure the database name is based on the company ID
    public static function getCustomColumns(): array
    {
        return ['id'];
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class, 'tenant_id');
    }

    public function activeSubscription()
    {
        return $this->subscription()->active()->first();
    }

    public function hasActiveSubscription(): bool
    {
        $subscription = $this->activeSubscription();

        return $subscription ? $subscription->isActive() : false;
    }
}