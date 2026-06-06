<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Estimate (a.k.a. quotation).
 *
 * Lives entirely in the tenant DB. Has zero impact on the General Ledger;
 * only when an admin clicks "Convert to Invoice" does the system create a
 * real Invoice record (and that invoice, when posted, triggers journals).
 */
class Estimate extends Model
{
    use HasFactory, SoftDeletes, HasUuid, Auditable;

    /** @var list<string> */
    public const STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'];

    /** Statuses where the estimate is still "live" and the user can edit it. */
    public const EDITABLE_STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired'];

    protected $fillable = [
        'estimate_number',
        'currency',
        'exchange_rate',
        'customer_id',
        'issue_date',
        'expiry_date',
        'status',
        'amount_before_tax',
        'discount_total',
        'tax_amount',
        'shipping_amount',
        'rounding_adjustment',
        'total_amount',
        'customer_notes',
        'private_notes',
        'converted_invoice_id',
        'created_by',
        'last_emailed_at',
        'last_emailed_to',
        'last_emailed_status',
        'last_emailed_error',
    ];

    protected function casts(): array
    {
        return [
            'issue_date'           => 'date',
            'expiry_date'          => 'date',
            'amount_before_tax'    => 'decimal:2',
            'discount_total'       => 'decimal:2',
            'tax_amount'           => 'decimal:2',
            'shipping_amount'      => 'decimal:2',
            'rounding_adjustment'  => 'decimal:2',
            'total_amount'         => 'decimal:2',
            'exchange_rate'        => 'decimal:6',
            'last_emailed_at'      => 'datetime',
        ];
    }

    public function items()
    {
        return $this->hasMany(EstimateItem::class)->orderBy('display_order');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The Invoice that was generated from this estimate, if any.
     * Soft FK on the same tenant DB.
     */
    public function convertedInvoice()
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE_STATUSES, true);
    }

    public function isConverted(): bool
    {
        return $this->status === 'converted';
    }

    public function isExpired(): bool
    {
        if ($this->status === 'converted') {
            return false;
        }
        return $this->expiry_date && $this->expiry_date->isPast();
    }
}
