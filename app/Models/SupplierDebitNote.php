<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierDebitNote extends Model
{
    use SoftDeletes, HasUuid, Auditable;

    protected $fillable = [
        'bill_id',
        'supplier_id',
        'sdn_number',
        'issue_date',
        'reason_code',
        'reason_description',
        'amount_before_tax',
        'tax_amount',
        'total_amount',
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
        return $this->hasMany(SupplierDebitNoteItem::class);
    }
}
