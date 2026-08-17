<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use App\Services\CreditNoteService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierCreditNote extends Model
{
    use SoftDeletes, HasUuid, Auditable;

    protected $fillable = [
        'bill_id',
        'supplier_id',
        'scn_number',
        'issue_date',
        'reason_code',
        'reason_description',
        'amount_before_tax',
        'tax_amount',
        'total_amount',
        'applied_amount',
        'refunded_amount',
        'currency',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date:Y-m-d',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function items()
    {
        return $this->hasMany(SupplierCreditNoteItem::class);
    }

    public function applications()
    {
        return $this->hasMany(SupplierCreditNoteApplication::class);
    }

    public function refunds()
    {
        return $this->hasMany(SupplierCreditNoteRefund::class);
    }

    public function openAmount(): float
    {
        return CreditNoteService::unappliedAmount(
            (float) $this->total_amount,
            (float) $this->applied_amount,
            (float) ($this->refunded_amount ?? 0)
        );
    }
}
