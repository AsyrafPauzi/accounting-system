<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, CentralConnection, HasRoles, Auditable;

    /**
     * Central `users` rows are scoped by `tenant_id` to an organization.
     * Spatie roles (admin, accountant, etc.) apply per user. Tenant admins can add colleagues under
     * Settings → Team & roles; the first user per tenant still usually comes from registration.
     */

    protected $appends = ['role_name'];

    public function getRoleNameAttribute()
    {
        return $this->roles->first()?->name ?? 'User';
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'company_id',
        'tenant_id',
        'is_active',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'theme_preference',
    ];

    /**
     * Users with roles that may edit credit and risk-related customer fields.
     */
    public function canEditCreditAndRisk(): bool
    {
        return $this->hasAnyRole(['admin', 'accountant']);
    }

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'      => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password'               => 'hashed',
            'is_active'              => 'boolean',
        ];
    }
}
