<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalItem extends Model
{
    use SoftDeletes, HasUuid, Auditable;
    protected $fillable = ['journal_entry_id', 'account_id', 'account_code', 'debit', 'credit', 'description'];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
