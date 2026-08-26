<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    protected $fillable = [
        'name',
        'fiscal_year',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'is_active'   => 'boolean',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }
}
