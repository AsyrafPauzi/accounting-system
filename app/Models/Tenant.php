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
    
    protected $casts = [
        'company' => 'array',
    ];


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
        $data = $this->company ?? [];
        
        return [
            'name'     => $data['display_name'] ?? $data['legal_name'] ?? config('app.name'),
            'address'  => $data['street'] ?? config('invoice.company.address'),
            'city'     => $data['city'] ?? config('invoice.company.city'),
            'state'    => $data['state'] ?? config('invoice.company.state'),
            'zip'      => $data['postcode'] ?? config('invoice.company.zip'),
            'country'  => $data['country'] ?? config('invoice.company.country'),
            'phone'    => $data['phone'] ?? config('invoice.company.phone'),
            'email'    => $data['email'] ?? config('invoice.company.email'),
            'website'  => $data['website'] ?? config('invoice.company.website'),
            'tin'      => $data['tin'] ?? config('invoice.company.tin'),
            'brn'      => $data['brn'] ?? config('invoice.company.brn'),
            'currency' => $data['base_currency'] ?? config('invoice.company.currency', 'MYR'),
        ];
    }
}