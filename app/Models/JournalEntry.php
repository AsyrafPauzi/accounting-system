<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use SoftDeletes, HasUuid;
    protected $fillable = ['date', 'description', 'reference_type', 'reference_id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function items()
    {
        return $this->hasMany(JournalItem::class);
    }

    /**
     * Route to the source document (Invoice or Credit Note) when applicable.
     */
    public function getSourceRoute(): ?string
    {
        if (! $this->reference_id) {
            return null;
        }
        if ($this->reference_type === 'Invoice' || $this->reference_type === 'Invoice Payment') {
            return route('invoices.edit', $this->reference_id);
        }
        if ($this->reference_type === 'Credit Note') {
            return route('credit-notes.index');
        }
        if ($this->reference_type === 'Bill' || $this->reference_type === 'Bill Payment') {
            return route('bills.edit', $this->reference_id);
        }

        return null;
    }
}
