<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ArDeposit extends Model
{
    use SoftDeletes, HasUuid, Auditable;

    protected $fillable = [
        'customer_id',
        'amount',
        'applied_amount',
        'payment_date',
        'bank_account_code',
        'reference',
        'status',
        'notes',
        'created_by',
        'refunded_amount',
        'forfeited_amount',
    ];

    protected function casts(): array
    {
        return [
            'payment_date'   => 'date:Y-m-d',
            'amount'           => 'decimal:2',
            'applied_amount'   => 'decimal:2',
            'refunded_amount'  => 'decimal:2',
            'forfeited_amount' => 'decimal:2',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function applications()
    {
        return $this->hasMany(ArDepositApplication::class);
    }

    public function openAmount(): float
    {
        return round(
            (float) $this->amount
            - (float) $this->applied_amount
            - (float) ($this->refunded_amount ?? 0)
            - (float) ($this->forfeited_amount ?? 0),
            2
        );
    }
}
