<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecurringBillItem extends Model
{
    protected $fillable = [
        'recurring_bill_id',
        'account_code',
        'description',
        'quantity',
        'unit_amount',
        'amount',
        'sort_order',
    ];

    public function recurringBill()
    {
        return $this->belongsTo(RecurringBill::class);
    }
}
