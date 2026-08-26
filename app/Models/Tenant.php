<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, CentralConnection, Auditable, HasDomains;

    protected $fillable = [
        'id',
        'provision_status',
        'provision_error',
        'provisioned_at',
    ];

    protected function casts(): array
    {
        return [
            'provisioned_at' => 'datetime',
        ];
    }

    // This makes sure the database name is based on the company ID
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'provision_status',
            'provision_error',
            'provisioned_at',
        ];
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

    /**
     * Whether the tenant database is available for SME routes.
     *
     * Legacy rows (pre async-provisioning migration) had no
     * `provision_status` column — treat null the same as `ready`.
     */
    public function isProvisioned(): bool
    {
        $status = $this->provision_status;

        return $status === 'ready' || $status === null;
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
            'tin'         => $this->tin ?: config('invoice.company.tin'),
            'brn'         => $this->brn ?: config('invoice.company.brn'),
            'sst'         => $this->sst_number ?: '',
            'msic'        => $this->msic_code ?: '',
            'currency'    => $this->base_currency ?: config('invoice.company.currency', 'MYR'),
            'brand_color' => $this->invoice_brand_color ?: '#0f172a',
            'logo_url'    => $this->invoice_logo_url ?: '',
        ];
    }

}

/*
 * UI display language is stored as `language` in the JSON data column —
 * Stancl Tenancy automatically maps `$tenant->language` to/from `data->language`.
 * Allowed values: 'en' | 'ms'. Read via `$tenant->language ?? 'en'`.
 */