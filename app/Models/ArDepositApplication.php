<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArDepositApplication extends Model
{
    protected $fillable = [
        'ar_deposit_id',
        'invoice_id',
        'amount',
    ];

    public function deposit()
    {
        return $this->belongsTo(ArDeposit::class, 'ar_deposit_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
