<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Route;

class JournalEntry extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'date',
        'description',
        'reference_number',
        'type',
        'status',
        'reference_type',
        'reference_id',
    ];

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
     * Route to the source document when applicable; always falls back to GL show.
     */
    public function getSourceRoute(): string
    {
        return $this->resolveSourceRoute() ?? route('general-ledger.show', $this->id);
    }

    public function getSourceLabel(): string
    {
        $ref = trim((string) ($this->reference_number ?? ''));

        return match ($this->reference_type) {
            'Invoice', 'Invoice Payment' => $ref !== '' ? "Invoice {$ref}" : 'Invoice',
            'Credit Note', 'Credit Note Refund' => $ref !== '' ? "Credit note {$ref}" : 'Credit note',
            'Debit Note' => $ref !== '' ? "Debit note {$ref}" : 'Debit note',
            'Bill', 'Bill Payment' => $ref !== '' ? "Bill {$ref}" : 'Bill',
            'Supplier Credit Note', 'Supplier Credit Note Refund' => $ref !== '' ? "Supplier credit note {$ref}" : 'Supplier credit note',
            'Supplier Debit Note' => $ref !== '' ? "Supplier debit note {$ref}" : 'Supplier debit note',
            'AR Deposit', 'AR Deposit Application', 'AR Deposit Refund', 'AR Deposit Forfeit' => $ref !== '' ? "AR deposit {$ref}" : 'AR deposit',
            'AP Deposit', 'AP Deposit Application' => $ref !== '' ? "AP deposit {$ref}" : 'AP deposit',
            'Manual' => $ref !== '' ? "Manual journal {$ref}" : 'Manual journal',
            default => match ($this->type) {
                'deposit' => 'Deposit',
                'withdrawal' => 'Withdrawal',
                'payroll' => 'Payroll',
                'manual' => $ref !== '' ? "Manual journal {$ref}" : 'Manual journal',
                default => $this->reference_type ?: 'Journal entry',
            },
        };
    }

    protected function resolveSourceRoute(): ?string
    {
        if ($this->type === 'manual' || $this->reference_type === 'Manual') {
            if ($this->status !== 'posted' && Route::has('journal.edit')) {
                return route('journal.edit', $this->id);
            }

            return null;
        }

        if (! $this->reference_id) {
            return null;
        }

        return match ($this->reference_type) {
            'Invoice' => $this->safeRoute('invoices.show', Invoice::class, $this->reference_id),
            'Invoice Payment' => $this->paymentParentRoute(InvoicePayment::class, 'invoice_id', 'invoices.show', Invoice::class),
            'Credit Note', 'Credit Note Refund' => $this->safeRoute('credit-notes.show', CreditNote::class, $this->reference_id),
            'Debit Note' => $this->safeRoute('debit-notes.show', DebitNote::class, $this->reference_id),
            'Bill' => $this->safeRoute('bills.show', Bill::class, $this->reference_id),
            'Bill Payment' => $this->paymentParentRoute(BillPayment::class, 'bill_id', 'bills.show', Bill::class),
            'Supplier Credit Note', 'Supplier Credit Note Refund' => $this->safeRoute('supplier-credit-notes.show', SupplierCreditNote::class, $this->reference_id),
            'Supplier Debit Note' => $this->safeRoute('supplier-debit-notes.show', SupplierDebitNote::class, $this->reference_id),
            'AR Deposit', 'AR Deposit Application', 'AR Deposit Refund', 'AR Deposit Forfeit' => $this->safeRoute('ar-deposits.show', ArDeposit::class, $this->reference_id),
            'AP Deposit', 'AP Deposit Application' => $this->safeRoute('ap-deposits.show', ApDeposit::class, $this->reference_id),
            default => null,
        };
    }

    protected function safeRoute(string $routeName, string $modelClass, int $id): ?string
    {
        if (! Route::has($routeName)) {
            return null;
        }

        try {
            return $modelClass::query()->whereKey($id)->exists()
                ? route($routeName, $id)
                : null;
        } catch (\Illuminate\Database\QueryException) {
            return null;
        }
    }

    /**
     * @param  class-string  $paymentClass
     * @param  class-string  $parentModelClass
     */
    protected function paymentParentRoute(string $paymentClass, string $parentKey, string $routeName, string $parentModelClass): ?string
    {
        if (! Route::has($routeName)) {
            return null;
        }

        $payment = $paymentClass::query()->find($this->reference_id);
        $parentId = $payment?->{$parentKey};

        if (! $parentId) {
            return null;
        }

        try {
            return $parentModelClass::query()->whereKey($parentId)->exists()
                ? route($routeName, $parentId)
                : null;
        } catch (\Illuminate\Database\QueryException) {
            return null;
        }
    }
}
