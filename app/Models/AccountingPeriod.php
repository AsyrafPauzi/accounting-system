<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingPeriod extends Model
{
    protected $fillable = [
        'start_date',
        'end_date',
        'label',
        'status',
        'closed_at',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
            'closed_at'  => 'datetime',
        ];
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
