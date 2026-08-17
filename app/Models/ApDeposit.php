<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApDeposit extends Model
{
    use SoftDeletes, HasUuid, Auditable;

    protected $fillable = [
        'supplier_id',
        'amount',
        'applied_amount',
        'payment_date',
        'bank_account_code',
        'reference',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date'   => 'date:Y-m-d',
            'amount'         => 'decimal:2',
            'applied_amount' => 'decimal:2',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function applications()
    {
        return $this->hasMany(ApDepositApplication::class);
    }

    public function openAmount(): float
    {
        return round((float) $this->amount - (float) $this->applied_amount, 2);
    }
}
