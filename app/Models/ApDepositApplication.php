<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApDepositApplication extends Model
{
    protected $fillable = [
        'ap_deposit_id',
        'bill_id',
        'amount',
    ];

    public function deposit()
    {
        return $this->belongsTo(ApDeposit::class, 'ap_deposit_id');
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }
}
