<?php

namespace App\Services;

use App\Support\TaxCodeDefaults;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builds SST-02 / SST-02A helper rows grouped by tax code (SR-8, ST-10, ES, ZRL).
 *
 * Filing helper only — figures for your return; verify before submitting to MyTax.
 */
class Sst02ExportService
{
    /** @var list<string> */
    private const CODES = ['SR-8', 'ST-10', 'ES', 'ZRL'];

    /**
     * @return list<array{
     *     tax_code: string,
     *     taxable_sales: float,
     *     output_tax: float,
     *     taxable_purchases: float,
     *     input_tax: float,
     *     net_tax: float,
     *     cn_adjustment: float,
     *     dn_adjustment: float,
     * }>
     */
    public function build(string $start, string $end): array
    {
        TaxCodeDefaults::seedMissing();

        /** @var array<string, array<string, float>> $rows */
        $rows = [];
        foreach (self::CODES as $code) {
            $rows[$code] = $this->emptyRow($code);
        }

        $this->aggregateInvoiceSales($rows, $start, $end);
        $this->aggregateBillPurchases($rows, $start, $end);
        $this->aggregateCreditNoteAdjustments($rows, $start, $end);
        $this->aggregateDebitNoteAdjustments($rows, $start, $end);

        return array_values(array_map(function (array $row): array {
            $row['taxable_sales'] = round($row['taxable_sales'], 2);
            $row['output_tax'] = round($row['output_tax'], 2);
            $row['taxable_purchases'] = round($row['taxable_purchases'], 2);
            $row['input_tax'] = round($row['input_tax'], 2);
            $row['cn_adjustment'] = round($row['cn_adjustment'], 2);
            $row['dn_adjustment'] = round($row['dn_adjustment'], 2);
            $row['net_tax'] = round($row['output_tax'] - $row['input_tax'], 2);

            return $row;
        }, $rows));
    }

    /**
     * @return array<string, float|string>
     */
    private function emptyRow(string $code): array
    {
        return [
            'tax_code'           => $code,
            'taxable_sales'      => 0.0,
            'output_tax'         => 0.0,
            'taxable_purchases'  => 0.0,
            'input_tax'          => 0.0,
            'net_tax'            => 0.0,
            'cn_adjustment'      => 0.0,
            'dn_adjustment'      => 0.0,
        ];
    }

    /**
     * @param  array<string, array<string, float|string>>  $rows
     */
    private function aggregateInvoiceSales(array &$rows, string $start, string $end): void
    {
        $query = DB::table('invoice_items as ii')
            ->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->whereNotIn('i.status', ['draft', 'void'])
            ->whereDate('i.issue_date', '>=', $start)
            ->whereDate('i.issue_date', '<=', $end)
            ->whereNull('i.deleted_at')
            ->whereNull('ii.deleted_at');

        if (Schema::hasColumn('invoice_items', 'tax_code_id')) {
            $query->leftJoin('tax_codes as tc', 'tc.id', '=', 'ii.tax_code_id');
        }

        $select = [
            DB::raw('SUM(ii.amount) as taxable'),
            DB::raw('SUM(ii.amount * (ii.tax_rate / 100)) as tax'),
        ];

        if (Schema::hasColumn('invoice_items', 'tax_code_id')) {
            $select[] = DB::raw('COALESCE(tc.code, '.$this->taxCodeCaseSql('ii.tax_rate').') as tax_code');
            $query->groupBy(DB::raw('COALESCE(tc.code, '.$this->taxCodeCaseSql('ii.tax_rate').')'));
        } else {
            $select[] = DB::raw($this->taxCodeCaseSql('ii.tax_rate').' as tax_code');
            $query->groupBy(DB::raw($this->taxCodeCaseSql('ii.tax_rate')));
        }

        foreach ($query->select($select)->get() as $row) {
            $code = $this->normalizeCode((string) $row->tax_code);
            $rows[$code]['taxable_sales'] += (float) $row->taxable;
            $rows[$code]['output_tax'] += (float) $row->tax;
        }
    }

    /**
     * @param  array<string, array<string, float|string>>  $rows
     */
    private function aggregateBillPurchases(array &$rows, string $start, string $end): void
    {
        if (Schema::hasTable('bill_items') && Schema::hasColumn('bill_items', 'tax_rate')) {
            $query = DB::table('bill_items as bi')
                ->join('bills as b', 'b.id', '=', 'bi.bill_id')
                ->whereNotIn('b.status', ['draft', 'void'])
                ->whereDate('b.bill_date', '>=', $start)
                ->whereDate('b.bill_date', '<=', $end)
                ->whereNull('b.deleted_at')
                ->whereNull('bi.deleted_at');

            if (Schema::hasColumn('bill_items', 'tax_code_id')) {
                $query->leftJoin('tax_codes as tc', 'tc.id', '=', 'bi.tax_code_id');
            }

            $select = [
                DB::raw('SUM(bi.amount) as taxable'),
                DB::raw('SUM(bi.amount * (bi.tax_rate / 100)) as tax'),
            ];

            if (Schema::hasColumn('bill_items', 'tax_code_id')) {
                $select[] = DB::raw('COALESCE(tc.code, '.$this->taxCodeCaseSql('bi.tax_rate').') as tax_code');
                $query->groupBy(DB::raw('COALESCE(tc.code, '.$this->taxCodeCaseSql('bi.tax_rate').')'));
            } else {
                $select[] = DB::raw($this->taxCodeCaseSql('bi.tax_rate').' as tax_code');
                $query->groupBy(DB::raw($this->taxCodeCaseSql('bi.tax_rate')));
            }

            foreach ($query->select($select)->get() as $row) {
                $code = $this->normalizeCode((string) $row->tax_code);
                $rows[$code]['taxable_purchases'] += (float) $row->taxable;
                $rows[$code]['input_tax'] += (float) $row->tax;
            }

            return;
        }

        $bills = DB::table('bills')
            ->whereNotIn('status', ['draft', 'void'])
            ->whereDate('bill_date', '>=', $start)
            ->whereDate('bill_date', '<=', $end)
            ->whereNull('deleted_at')
            ->select([
                DB::raw('SUM(total_amount - tax_amount) as taxable'),
                DB::raw('SUM(tax_amount) as tax'),
            ])
            ->first();

        $taxable = (float) ($bills->taxable ?? 0);
        $tax = (float) ($bills->tax ?? 0);
        if ($taxable !== 0.0 || $tax !== 0.0) {
            $rows['SR-8']['taxable_purchases'] += $taxable;
            $rows['SR-8']['input_tax'] += $tax;
        }
    }

    /**
     * @param  array<string, array<string, float|string>>  $rows
     */
    private function aggregateCreditNoteAdjustments(array &$rows, string $start, string $end): void
    {
        if (! Schema::hasTable('credit_note_items') || ! Schema::hasTable('credit_notes')) {
            return;
        }

        $query = DB::table('credit_note_items as cni')
            ->join('credit_notes as cn', 'cn.id', '=', 'cni.credit_note_id')
            ->where('cn.status', '!=', 'void')
            ->whereDate('cn.issue_date', '>=', $start)
            ->whereDate('cn.issue_date', '<=', $end)
            ->whereNull('cn.deleted_at');

        if (Schema::hasColumn('credit_note_items', 'deleted_at')) {
            $query->whereNull('cni.deleted_at');
        }

        if (Schema::hasColumn('credit_note_items', 'tax_code_id')) {
            $query->leftJoin('tax_codes as tc', 'tc.id', '=', 'cni.tax_code_id');
        }

        $select = [
            DB::raw('SUM(cni.amount * (cni.tax_rate / 100)) as tax'),
        ];

        if (Schema::hasColumn('credit_note_items', 'tax_code_id')) {
            $select[] = DB::raw('COALESCE(tc.code, '.$this->taxCodeCaseSql('cni.tax_rate').') as tax_code');
            $query->groupBy(DB::raw('COALESCE(tc.code, '.$this->taxCodeCaseSql('cni.tax_rate').')'));
        } else {
            $select[] = DB::raw($this->taxCodeCaseSql('cni.tax_rate').' as tax_code');
            $query->groupBy(DB::raw($this->taxCodeCaseSql('cni.tax_rate')));
        }

        foreach ($query->select($select)->get() as $row) {
            $code = $this->normalizeCode((string) $row->tax_code);
            $rows[$code]['cn_adjustment'] += (float) $row->tax;
        }
    }

    /**
     * @param  array<string, array<string, float|string>>  $rows
     */
    private function aggregateDebitNoteAdjustments(array &$rows, string $start, string $end): void
    {
        if (! Schema::hasTable('debit_note_items') || ! Schema::hasTable('debit_notes')) {
            return;
        }

        $query = DB::table('debit_note_items as dni')
            ->join('debit_notes as dn', 'dn.id', '=', 'dni.debit_note_id')
            ->where('dn.status', '!=', 'void')
            ->whereDate('dn.issue_date', '>=', $start)
            ->whereDate('dn.issue_date', '<=', $end)
            ->whereNull('dn.deleted_at');

        if (Schema::hasColumn('debit_note_items', 'deleted_at')) {
            $query->whereNull('dni.deleted_at');
        }

        if (Schema::hasColumn('debit_note_items', 'tax_code_id')) {
            $query->leftJoin('tax_codes as tc', 'tc.id', '=', 'dni.tax_code_id');
        }

        $select = [
            DB::raw('SUM(dni.amount * (dni.tax_rate / 100)) as tax'),
        ];

        if (Schema::hasColumn('debit_note_items', 'tax_code_id')) {
            $select[] = DB::raw('COALESCE(tc.code, '.$this->taxCodeCaseSql('dni.tax_rate').') as tax_code');
            $query->groupBy(DB::raw('COALESCE(tc.code, '.$this->taxCodeCaseSql('dni.tax_rate').')'));
        } else {
            $select[] = DB::raw($this->taxCodeCaseSql('dni.tax_rate').' as tax_code');
            $query->groupBy(DB::raw($this->taxCodeCaseSql('dni.tax_rate')));
        }

        foreach ($query->select($select)->get() as $row) {
            $code = $this->normalizeCode((string) $row->tax_code);
            $rows[$code]['dn_adjustment'] += (float) $row->tax;
        }
    }

    private function taxCodeCaseSql(string $rateColumn): string
    {
        return "CASE
            WHEN {$rateColumn} >= 9.5 THEN 'ST-10'
            WHEN {$rateColumn} >= 7.5 THEN 'SR-8'
            WHEN {$rateColumn} > 0 THEN 'SR-8'
            ELSE 'ES'
        END";
    }

    private function normalizeCode(string $code): string
    {
        $code = strtoupper(trim($code));

        return in_array($code, self::CODES, true) ? $code : 'ES';
    }
}
