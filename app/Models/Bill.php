<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUuid;
use App\Services\BillService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bill extends Model
{
    use HasFactory, SoftDeletes, HasUuid, Auditable;
    
    protected $appends = [
        'balance_due',
        'supplier_name',
        'supplier_invoice_url',
        'payment_receipt_url',
    ];

    public function getSupplierInvoiceUrlAttribute(): ?string
    {
        return $this->documentUrlForSlot('supplier_invoice');
    }

    public function getPaymentReceiptUrlAttribute(): ?string
    {
        return $this->documentUrlForSlot('payment_receipt');
    }

    protected function documentUrlForSlot(string $slot): ?string
    {
        $column = $slot === 'payment_receipt' ? 'payment_receipt_path' : 'supplier_invoice_path';
        $path = $this->getAttributes()[$column] ?? null;
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        try {
            if ($this->id) {
                return route('bills.document', $this->id).'?slot='.urlencode($slot);
            }

            return route('bills.receipt', 0).'?path='.urlencode($path);
        } catch (\Exception $e) {
            return \Illuminate\Support\Facades\Storage::url($path);
        }
    }

    protected $fillable = [
        'bill_number', 'supplier_id', 'purchase_order_id', 'goods_receipt_id',
        'bill_date', 'due_date', 'status', 'purchase_kind',
        'total_amount', 'amount_paid', 'tax_amount', 'currency', 'exchange_rate',
        'private_notes', 'reference', 'created_by',
        'supplier_invoice_path', 'payment_receipt_path', 'ocr_status', 'ocr_data', 'audit_status',
        'audited_at', 'audited_by',
        'lhdn_status', 'lhdn_uuid', 'lhdn_long_id', 'lhdn_submitted_at',
        'lhdn_cancelled_at', 'lhdn_reject_reason', 'lhdn_qr_url',
    ];

    protected function casts(): array
    {
        return [
            'bill_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'ocr_data' => 'array',
            'audited_at' => 'datetime',
            'lhdn_submitted_at' => 'datetime',
            'lhdn_cancelled_at' => 'datetime',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function items()
    {
        return $this->hasMany(BillItem::class)->orderBy('sort_order');
    }

    public function payments()
    {
        return $this->hasMany(BillPayment::class);
    }

    public function documentVersions()
    {
        return $this->hasMany(BillDocumentVersion::class)->latest('id');
    }

    public function creditNoteApplications()
    {
        return $this->hasMany(SupplierCreditNoteApplication::class);
    }

    public function depositApplications()
    {
        return $this->hasMany(ApDepositApplication::class);
    }

    public function getBalanceDueAttribute(): float
    {
        if (in_array($this->status, ['draft', 'void'], true)) {
            return 0.0;
        }

        return app(BillService::class)->remainingBalance($this);
    }

    public function getSupplierNameAttribute(): string
    {
        if (! $this->relationLoaded('supplier')) {
            return '—';
        }

        return $this->supplier?->name ?? '—';
    }
}

