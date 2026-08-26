<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Support\AccountingPeriodResolver;
use App\Support\JournalWriter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FxRevaluationService
{
    public const REFERENCE_TYPE_INVOICE = 'Invoice FX Revaluation';

    public const REFERENCE_TYPE_BILL = 'Bill FX Revaluation';

    public function __construct(
        private FxGainLossService $fx,
        private InvoiceService $invoices,
        private BillService $bills,
    ) {}

    /**
     * @param  array<string, float>  $rates  Currency code => base per 1 unit (e.g. USD => 4.80)
     * @return list<array{kind: string, id: int, number: string, adjustment: float}>
     */
    public function revaluateAll(string $monthEndDate, array $rates): array
    {
        $monthEnd = Carbon::parse($monthEndDate)->endOfMonth()->toDateString();
        AccountingPeriodResolver::assertOpenForDate($monthEnd);

        $posted = [];

        foreach ($this->openForeignInvoices() as $invoice) {
            $currency = strtoupper((string) $invoice->currency);
            $revalRate = $rates[$currency] ?? null;
            if ($revalRate === null || $revalRate <= 0) {
                continue;
            }

            if ($this->alreadyPosted(self::REFERENCE_TYPE_INVOICE, (int) $invoice->id, $monthEnd)) {
                continue;
            }

            $remaining = $this->invoices->remainingBalance($invoice);
            if ($remaining <= 0) {
                continue;
            }

            $docRate = (float) ($invoice->exchange_rate ?? 0);
            if ($docRate <= 0) {
                continue;
            }

            $adjustment = round($remaining * ($revalRate - $docRate), 2);
            $lines = $this->fx->unrealizedArLines($adjustment);
            if ($lines === []) {
                continue;
            }

            JournalWriter::postSystem([
                'date' => $monthEnd,
                'description' => 'FX revaluation: '.$invoice->invoice_number.' @ '.$revalRate,
                'reference_type' => self::REFERENCE_TYPE_INVOICE,
                'reference_id' => $invoice->id,
            ], $lines);

            $posted[] = [
                'kind' => 'invoice',
                'id' => (int) $invoice->id,
                'number' => (string) $invoice->invoice_number,
                'adjustment' => $adjustment,
            ];
        }

        foreach ($this->openForeignBills() as $bill) {
            $currency = strtoupper((string) $bill->currency);
            $revalRate = $rates[$currency] ?? null;
            if ($revalRate === null || $revalRate <= 0) {
                continue;
            }

            if ($this->alreadyPosted(self::REFERENCE_TYPE_BILL, (int) $bill->id, $monthEnd)) {
                continue;
            }

            $remaining = $this->bills->remainingBalance($bill);
            if ($remaining <= 0) {
                continue;
            }

            $docRate = (float) ($bill->exchange_rate ?? 0);
            if ($docRate <= 0) {
                continue;
            }

            $adjustment = round($remaining * ($revalRate - $docRate), 2);
            $lines = $this->fx->unrealizedApLines($adjustment);
            if ($lines === []) {
                continue;
            }

            JournalWriter::postSystem([
                'date' => $monthEnd,
                'description' => 'FX revaluation: '.$bill->bill_number.' @ '.$revalRate,
                'reference_type' => self::REFERENCE_TYPE_BILL,
                'reference_id' => $bill->id,
            ], $lines);

            $posted[] = [
                'kind' => 'bill',
                'id' => (int) $bill->id,
                'number' => (string) $bill->bill_number,
                'adjustment' => $adjustment,
            ];
        }

        return $posted;
    }

    /**
     * @return list<Invoice>
     */
    private function openForeignInvoices(): array
    {
        $base = $this->tenantBaseCurrency();

        return Invoice::query()
            ->whereIn('status', ['unpaid', 'partially paid'])
            ->whereNotNull('currency')
            ->where('currency', '!=', $base)
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * @return list<Bill>
     */
    private function openForeignBills(): array
    {
        $base = $this->tenantBaseCurrency();

        return Bill::query()
            ->whereIn('status', ['unpaid', 'partially paid'])
            ->whereNotNull('currency')
            ->where('currency', '!=', $base)
            ->orderBy('id')
            ->get()
            ->all();
    }

    private function alreadyPosted(string $referenceType, int $referenceId, string $monthEnd): bool
    {
        return JournalEntry::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->whereDate('date', $monthEnd)
            ->where('status', 'posted')
            ->exists();
    }

    private function tenantBaseCurrency(): string
    {
        if (function_exists('tenant') && tenant()) {
            return strtoupper((string) (tenant()->base_currency ?? 'MYR'));
        }

        return 'MYR';
    }
}
