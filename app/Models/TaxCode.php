<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxCode extends Model
{
    protected $fillable = [
        'code',
        'name',
        'rate',
        'type',
        'output_account_code',
        'input_account_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate'      => 'float',
            'is_active' => 'boolean',
        ];
    }
}
