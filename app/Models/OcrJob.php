<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OcrJob extends Model
{
    protected $fillable = [
        'file_path',
        'original_filename',
        'status',
        'parsed_data',
        'error_message',
        'bill_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'parsed_data' => 'array',
        ];
    }

    protected $appends = ['receipt_url'];

    public function getReceiptUrlAttribute(): ?string
    {
        $path = $this->file_path;
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        try {
            return route('bills.receipt', 0).'?path='.urlencode(ltrim($path, '/'));
        } catch (\Throwable) {
            return null;
        }
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isReviewable(): bool
    {
        return in_array($this->status, ['ready', 'failed'], true);
    }

    public function isRetryable(): bool
    {
        return $this->status === 'failed';
    }
}
