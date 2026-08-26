<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollEmployeeLine extends Model
{
    protected $fillable = [
        'journal_entry_id',
        'employee_id',
        'gross_salary',
        'employee_epf',
        'employer_epf',
        'employee_socso',
        'employer_socso',
        'employee_eis',
        'employer_eis',
        'pcb',
        'net_pay',
    ];

    protected function casts(): array
    {
        return [
            'gross_salary'   => 'decimal:2',
            'employee_epf'   => 'decimal:2',
            'employer_epf'   => 'decimal:2',
            'employee_socso' => 'decimal:2',
            'employer_socso' => 'decimal:2',
            'employee_eis'   => 'decimal:2',
            'employer_eis'   => 'decimal:2',
            'pcb'            => 'decimal:2',
            'net_pay'        => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
