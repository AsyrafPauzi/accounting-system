<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\PayrollEmployeeLine;
use InvalidArgumentException;

/**
 * Simplified PCB / CP39-style CSV for LHDN e-Data PCB monthly filing.
 */
class PcbExportService
{
    /** @var list<string> */
    public const HEADERS = [
        'Employee Name',
        'NRIC',
        'Tax Category',
        'Gross Remuneration',
        'PCB Amount',
    ];

    public function csvForJournal(JournalEntry $journal): string
    {
        $lines = PayrollEmployeeLine::query()
            ->with('employee')
            ->where('journal_entry_id', $journal->id)
            ->orderBy('id')
            ->get();

        if ($lines->isEmpty()) {
            throw new InvalidArgumentException('This payroll run has no employee breakdown to export.');
        }

        $rows = [$this->headersRow()];

        foreach ($lines as $line) {
            $employee = $line->employee;
            $rows[] = [
                $employee?->name ?? '',
                $employee?->nric ?? '',
                $employee?->tax_category ?? '1',
                $this->formatAmount($line->gross_salary),
                $this->formatAmount($line->pcb),
            ];
        }

        return $this->toCsv($rows);
    }

    /**
     * @return list<string>
     */
    public function headersRow(): array
    {
        return self::HEADERS;
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new InvalidArgumentException('Unable to build CSV export.');
        }

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    private function formatAmount(float|string|null $amount): string
    {
        return number_format((float) ($amount ?? 0), 2, '.', '');
    }
}
