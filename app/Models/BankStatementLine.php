<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    protected $fillable = [
        'bank_statement_id',
        'transaction_date',
        'description',
        'reference',
        'amount',
        'matched_journal_item_id',
        'match_status',
        'match_confidence',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'match_confidence' => 'decimal:2',
        ];
    }

    public function bankStatement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class);
    }

    public function matchedJournalItem(): BelongsTo
    {
        return $this->belongsTo(JournalItem::class, 'matched_journal_item_id');
    }
}
