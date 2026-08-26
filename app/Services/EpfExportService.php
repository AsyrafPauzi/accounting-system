<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\PayrollEmployeeLine;
use InvalidArgumentException;

/**
 * CSV layout compatible with KWSP i-Akaun bulk contribution import (simplified).
 */
class EpfExportService
{
    /** @var list<string> */
    public const HEADERS = [
        'Employer EPF No',
        'Member EPF No',
        'Member IC No',
        'Member Name',
        'Wages',
        'Employee Contribution',
        'Employer Contribution',
    ];

    public function csvForJournal(JournalEntry $journal, ?string $employerEpfNo = null): string
    {
        $lines = PayrollEmployeeLine::query()
            ->with('employee')
            ->where('journal_entry_id', $journal->id)
            ->orderBy('id')
            ->get();

        if ($lines->isEmpty()) {
            throw new InvalidArgumentException('This payroll run has no employee breakdown to export.');
        }

        $employerEpfNo ??= '';

        $rows = [$this->headersRow()];

        foreach ($lines as $line) {
            $employee = $line->employee;
            $rows[] = [
                $employerEpfNo,
                $employee?->epf_number ?? '',
                $employee?->nric ?? '',
                $employee?->name ?? '',
                $this->formatAmount($line->gross_salary),
                $this->formatAmount($line->employee_epf),
                $this->formatAmount($line->employer_epf),
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
