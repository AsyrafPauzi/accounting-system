<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [
        'employee_number',
        'name',
        'nric',
        'epf_number',
        'tax_category',
        'basic_salary',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'is_active'    => 'boolean',
        ];
    }

    public function payrollLines(): HasMany
    {
        return $this->hasMany(PayrollEmployeeLine::class);
    }
}
