<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bill extends Model
{
    use HasFactory, SoftDeletes, HasUuid, Auditable;

    protected $fillable = [
        'bill_number', 'supplier_id', 'bill_date', 'due_date', 'status',
        'total_amount', 'amount_paid', 'tax_amount', 'currency',
        'private_notes', 'reference', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'due_date' => 'date',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'tax_amount' => 'decimal:2',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(BillItem::class)->orderBy('sort_order');
    }

    public function getBalanceDueAttribute(): float
    {
        if (in_array($this->status, ['draft', 'void'], true)) {
            return 0.0;
        }
        return max(0, (float) $this->total_amount - (float) $this->amount_paid);
    }
}
