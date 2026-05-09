<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, CentralConnection, Auditable;
    


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

    public function hasPlanPermission(string $permission): bool
    {
        $subscription = $this->activeSubscription();
        if (! $subscription || ! $subscription->plan) {
            return false;
        }

        return $subscription->plan->hasPermission($permission);
    }

    public function hasActiveSubscription(): bool
    {
        $subscription = $this->activeSubscription();

        return $subscription ? $subscription->isActive() : false;
    }

    public function getCompanyDetails(): array
    {
        return [
            'name'     => $this->display_name ?: ($this->legal_name ?: config('app.name')),
            'address'  => $this->street ?: config('invoice.company.address'),
            'city'     => $this->city ?: config('invoice.company.city'),
            'state'    => $this->state ?: config('invoice.company.state'),
            'zip'      => $this->postcode ?: config('invoice.company.zip'),
            'country'  => $this->country ?: config('invoice.company.country'),
            'phone'    => $this->phone ?: config('invoice.company.phone'),
            'email'    => $this->email ?: config('invoice.company.email'),
            'website'  => $this->website ?: config('invoice.company.website'),
            'tin'      => $this->tin ?: config('invoice.company.tin'),
            'brn'      => $this->brn ?: config('invoice.company.brn'),
            'currency' => $this->base_currency ?: config('invoice.company.currency', 'MYR'),
        ];
    }
}