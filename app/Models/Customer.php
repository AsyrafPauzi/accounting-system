<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Customer extends Model
{
    use HasFactory, SoftDeletes, HasUuid, Auditable;

    protected $fillable = [
        'name', 'code', 'industry', 'website', 'contact_person', 'phone', 'email',
        'tin', 'brn', 'identification_type', 'sst_number', 'credit_limit', 'credit_hold', 'payment_terms', 'currency',
        'risk_rating', 'segment', 'region', 'account_manager_id', 'parent_id',
        'billing_street', 'billing_city', 'billing_state', 'billing_zip', 'billing_country',
        'shipping_street', 'shipping_city', 'shipping_state', 'shipping_zip', 'shipping_country',
        'is_active', 'internal_notes', 'invoice_delivery_method', 'send_statement',
    ];

    protected $casts = [
        'credit_hold' => 'boolean',
        'send_statement' => 'boolean',
    ];

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Human-readable reason this customer cannot be deleted, or null if deletion is allowed.
     * Only non–soft-deleted invoices and credit notes count.
     */
    public function deletionBlockedReason(): ?string
    {
        if ($this->invoices()->exists()) {
            return 'This customer is linked to one or more invoices. Remove or void those invoices first.';
        }

        if (CreditNote::query()->where('customer_id', $this->id)->exists()) {
            return 'This customer is linked to credit notes. Resolve those documents first.';
        }

        if (static::query()->where('parent_id', $this->id)->exists()) {
            return 'This customer has subsidiary accounts. Reassign those customers first.';
        }

        return null;
    }

    public function accountManager()
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function parent()
    {
        return $this->belongsTo(Customer::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Customer::class, 'parent_id');
    }

    public function contacts()
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(CustomerAuditLog::class)->orderByDesc('created_at');
    }

    /**
     * Sum of (total_amount - amount_paid) for non-draft, non-void invoices.
     */
    public function getBalanceAttribute(): float
    {
        return (float) $this->invoices()
            ->whereNotIn('status', ['draft', 'void'])
            ->sum(DB::raw('total_amount - amount_paid'));
    }
}