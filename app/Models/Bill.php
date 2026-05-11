<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bill extends Model
{
    use HasFactory, SoftDeletes, HasUuid, Auditable;
    
    protected $appends = ['balance_due', 'supplier_name', 'receipt_url'];

    public function getReceiptUrlAttribute(): ?string
    {
        if (!$this->receipt_path) {
            return null;
        }

        // If the path already starts with http, it's already a full URL (maybe from a previous bug)
        if (str_starts_with($this->receipt_path, 'http')) {
            return $this->receipt_path;
        }

        // Clean up the path if it has /storage/ or storage/ at the beginning
        $path = $this->receipt_path;
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        try {
            return route('bills.receipt', $this->id) . '?path=' . urlencode($path);
        } catch (\Exception $e) {
            return \Illuminate\Support\Facades\Storage::url($path);
        }
    }


    protected $fillable = [
        'bill_number', 'supplier_id', 'bill_date', 'due_date', 'status',
        'total_amount', 'amount_paid', 'tax_amount', 'currency',
        'private_notes', 'reference', 'created_by',
        'receipt_path', 'ocr_status', 'ocr_data', 'audit_status',
        'audited_at', 'audited_by',
    ];

    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'due_date' => 'date',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'ocr_data' => 'array',
            'audited_at' => 'datetime',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(BillItem::class)->orderBy('sort_order');
    }

    public function getBalanceDueAttribute(): float
    {
        if (in_array($this->status, ['draft', 'void'], true)) {
            return 0.0;
        }
        return max(0, (float) $this->total_amount - (float) $this->amount_paid);
    }

    public function getSupplierNameAttribute(): string
    {
        return $this->supplier?->name ?? '—';
    }
}

