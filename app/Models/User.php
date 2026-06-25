<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Notifications\Auth\VerifyEmail as VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Traits\HasRoles;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class User extends Authenticatable implements MustVerifyEmail
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
        'two_factor_pending_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'theme_preference',
        'firm_id',
        'firm_role',
        'privacy_accepted_at',
        'privacy_accepted_version',
        'data_exported_at',
        'deletion_requested_at',
        'welcomed_at',
        'verify_reminder_at',
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
        'two_factor_pending_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'      => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_secret'       => 'encrypted',
            'two_factor_pending_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'array',
            'privacy_accepted_at'    => 'datetime',
            'data_exported_at'       => 'datetime',
            'deletion_requested_at'  => 'datetime',
            'welcomed_at'            => 'datetime',
            'verify_reminder_at'     => 'datetime',
            'password'               => 'hashed',
            'is_active'              => 'boolean',
        ];
    }

    /**
     * The firm this user belongs to as staff. Set for firm-owners and
     * firm-staff; null for normal SME tenant users.
     */
    public function firm()
    {
        return $this->belongsTo(Firm::class, 'firm_id');
    }

    /**
     * Convenience: is this user part of an accountancy firm?
     */
    public function isFirmUser(): bool
    {
        return ! empty($this->firm_id);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());

        Log::info('Email verification notification sent', [
            'user_id' => $this->id,
            'email' => $this->email,
            'account_type' => $this->isFirmUser() ? 'practice' : 'business',
            'firm_id' => $this->firm_id,
            'tenant_id' => $this->tenant_id,
        ]);
    }

    /**
     * Convenience: is this user the firm-owner (vs. firm-staff)?
     * Firm-owners are the only ones allowed to manage billing,
     * invite clients, or change firm settings.
     */
    public function isFirmOwner(): bool
    {
        return $this->isFirmUser() && $this->firm_role === 'owner';
    }

    /**
     * Can this user perform tenant-admin operations (edit company
     * settings, manage team, change plan) on the *currently active*
     * tenant?
     *
     * Two paths grant admin authority on a tenant:
     *
     *   1. SME users with the `admin` / `super-admin` role on their own
     *      tenant — the classic single-org bookkeeper.
     *
     *   2. Firm users who have been granted `admin` permission_level
     *      via the `firm_clients` pivot when they act into a client.
     *      This is the explicit "we hired this firm to manage our
     *      books" handshake — `editor` and `viewer` levels deliberately
     *      do *not* unlock admin settings.
     *
     * Both checks require tenancy to actually be initialised. We never
     * assume — better to refuse than to leak across tenants.
     */
    public function canAdminCurrentTenant(): bool
    {
        if (! function_exists('tenancy') || ! tenancy()->initialized) {
            return false;
        }

        $tenantId = optional(tenancy()->tenant)->getKey();
        if (! $tenantId) {
            return false;
        }

        // Path 1: SME admin / super-admin on their own tenant.
        if ($this->tenant_id === $tenantId && $this->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        // Path 2: Firm user with admin pivot level on this client.
        if ($this->isFirmUser()) {
            return FirmClient::query()
                ->where('firm_id', $this->firm_id)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->where('permission_level', 'admin')
                ->exists();
        }

        return false;
    }
}
