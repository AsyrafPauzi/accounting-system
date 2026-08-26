<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetLine extends Model
{
    protected $fillable = [
        'budget_id',
        'account_code',
        'month',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'month'  => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }
}
