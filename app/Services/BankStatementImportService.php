<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Services\Ocr\PdfPreprocessor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class BankStatementImportService
{
    /**
     * Import a generic CSV with date, description, and amount columns.
     *
     * @return array{statement: BankStatement, line_count: int}
     */
    public function importFromCsv(
        string $csvContents,
        Account $account,
        ?string $storedPath = null,
        ?float $openingBalance = null,
        ?float $closingBalance = null,
    ): array {
        $rows = $this->parseCsv($csvContents);

        if ($rows === []) {
            throw new InvalidArgumentException('CSV contains no transaction rows.');
        }

        $dates = array_column($rows, 'transaction_date');
        $periodStart = min($dates);
        $periodEnd = max($dates);

        $lineTotal = array_sum(array_column($rows, 'amount'));
        $opening = $openingBalance ?? 0.0;
        $closing = $closingBalance ?? round($opening + $lineTotal, 2);

        return DB::transaction(function () use ($account, $rows, $storedPath, $periodStart, $periodEnd, $opening, $closing) {
            $statement = BankStatement::create([
                'account_id' => $account->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'opening_balance' => $opening,
                'closing_balance' => $closing,
                'source' => 'csv',
                'file_path' => $storedPath,
                'status' => 'open',
            ]);

            foreach ($rows as $row) {
                BankStatementLine::create([
                    'bank_statement_id' => $statement->id,
                    'transaction_date' => $row['transaction_date'],
                    'description' => $row['description'],
                    'reference' => $row['reference'],
                    'amount' => $row['amount'],
                    'match_status' => 'unmatched',
                ]);
            }

            return [
                'statement' => $statement->fresh(['lines']),
                'line_count' => count($rows),
            ];
        });
    }

    /**
     * Import a bank statement PDF (text-based statements only).
     *
     * @return array{statement: BankStatement, line_count: int}
     */
    public function importFromPdf(
        string $pdfAbsolutePath,
        Account $account,
        ?string $storedPath = null,
        ?float $openingBalance = null,
        ?float $closingBalance = null,
    ): array {
        $rows = $this->parsePdf($pdfAbsolutePath);

        if ($rows === []) {
            throw new InvalidArgumentException('PDF contains no recognizable transaction rows.');
        }

        $dates = array_column($rows, 'transaction_date');
        $periodStart = min($dates);
        $periodEnd = max($dates);

        $lineTotal = array_sum(array_column($rows, 'amount'));
        $opening = $openingBalance ?? 0.0;
        $closing = $closingBalance ?? round($opening + $lineTotal, 2);

        return DB::transaction(function () use ($account, $rows, $storedPath, $periodStart, $periodEnd, $opening, $closing) {
            $statement = BankStatement::create([
                'account_id' => $account->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'opening_balance' => $opening,
                'closing_balance' => $closing,
                'source' => 'pdf',
                'file_path' => $storedPath,
                'status' => 'open',
            ]);

            foreach ($rows as $row) {
                BankStatementLine::create([
                    'bank_statement_id' => $statement->id,
                    'transaction_date' => $row['transaction_date'],
                    'description' => $row['description'],
                    'reference' => $row['reference'],
                    'amount' => $row['amount'],
                    'match_status' => 'unmatched',
                ]);
            }

            return [
                'statement' => $statement->fresh(['lines']),
                'line_count' => count($rows),
            ];
        });
    }

    /**
     * @return list<array{transaction_date: string, description: ?string, reference: ?string, amount: float}>
     */
    public function parsePdf(string $pdfAbsolutePath): array
    {
        /** @var PdfPreprocessor $preprocessor */
        $preprocessor = app(PdfPreprocessor::class);
        $result = $preprocessor->preprocess($pdfAbsolutePath);

        if ($result['error']) {
            throw new InvalidArgumentException($result['error']);
        }

        if ($result['mode'] !== 'text' || ! filled($result['text'])) {
            throw new InvalidArgumentException('PDF appears to be image-only. Export CSV from your bank or use a text-based PDF.');
        }

        return $this->parseStatementText($result['text']);
    }

    /**
     * Parse plain-text bank statement lines (shared by PDF extraction and tests).
     *
     * @return list<array{transaction_date: string, description: ?string, reference: ?string, amount: float}>
     */
    public function parseStatementText(string $text): array
    {
        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line) ?? '');
            if ($line === '') {
                continue;
            }

            $parsed = $this->parseStatementLine($line);
            if ($parsed !== null) {
                $rows[] = $parsed;
            }
        }

        return $rows;
    }

    /**
     * @return array{transaction_date: string, description: ?string, reference: ?string, amount: float}|null
     */
    private function parseStatementLine(string $line): ?array
    {
        $patterns = [
            '/^(\d{4}-\d{2}-\d{2})\s+(.+?)\s+(-?\(?[\d,]+\.\d{2}\)?)\s*$/',
            '/^(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\s+(.+?)\s+(-?\(?[\d,]+\.\d{2}\)?)\s*$/',
            '/^(\d{1,2}\s+[A-Za-z]{3}\s+\d{4})\s+(.+?)\s+(-?\(?[\d,]+\.\d{2}\)?)\s*$/',
        ];

        foreach ($patterns as $pattern) {
            if (! preg_match($pattern, $line, $matches)) {
                continue;
            }

            try {
                $date = $this->parseDate($matches[1]);
            } catch (\Throwable) {
                continue;
            }

            try {
                $amount = $this->parseSignedAmount($matches[3]);
            } catch (\Throwable) {
                continue;
            }

            $description = trim($matches[2]) ?: null;

            return [
                'transaction_date' => $date,
                'description' => $description,
                'reference' => null,
                'amount' => $amount,
            ];
        }

        return null;
    }

    private function parseSignedAmount(string $value): float
    {
        $negative = str_contains($value, '(') && str_contains($value, ')');
        $clean = str_replace(['(', ')', ',', ' '], '', $value);
        $clean = str_replace(['RM', 'rm', '$'], '', $clean);

        if (! is_numeric($clean)) {
            throw new InvalidArgumentException("Invalid amount value: {$value}");
        }

        $amount = round((float) $clean, 2);

        return $negative ? -abs($amount) : $amount;
    }

    /**
     * @return list<array{transaction_date: string, description: ?string, reference: ?string, amount: float}>
     */
    public function parseCsv(string $csvContents): array
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new InvalidArgumentException('Unable to read CSV contents.');
        }

        fwrite($handle, $csvContents);
        rewind($handle);

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return [];
        }

        $map = $this->mapHeaders($header);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($this->isEmptyRow($data)) {
                continue;
            }

            $parsed = $this->parseRow($data, $map);
            if ($parsed !== null) {
                $rows[] = $parsed;
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<string|null>  $header
     * @return array{date: int, description: ?int, reference: ?int, amount: int}
     */
    private function mapHeaders(array $header): array
    {
        $normalized = array_map(fn ($col) => strtolower(trim((string) $col)), $header);

        $dateIdx = $this->findColumn($normalized, ['date', 'transaction date', 'txn date', 'posting date']);
        $descriptionIdx = $this->findColumn($normalized, ['description', 'details', 'narration', 'particulars']);
        $referenceIdx = $this->findColumn($normalized, ['reference', 'ref', 'ref no', 'reference no']);
        $amountIdx = $this->findColumn($normalized, ['amount', 'transaction amount', 'value']);

        if ($dateIdx === null || $amountIdx === null) {
            throw new InvalidArgumentException('CSV must include date and amount columns.');
        }

        return [
            'date' => $dateIdx,
            'description' => $descriptionIdx,
            'reference' => $referenceIdx,
            'amount' => $amountIdx,
        ];
    }

    /**
     * @param  list<string>  $candidates
     */
    private function findColumn(array $headers, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $idx = array_search($candidate, $headers, true);
            if ($idx !== false) {
                return (int) $idx;
            }
        }

        return null;
    }

    /**
     * @param  list<string|null>  $data
     * @param  array{date: int, description: ?int, reference: ?int, amount: int}  $map
     * @return array{transaction_date: string, description: ?string, reference: ?string, amount: float}|null
     */
    private function parseRow(array $data, array $map): ?array
    {
        $dateRaw = trim((string) ($data[$map['date']] ?? ''));
        $amountRaw = trim((string) ($data[$map['amount']] ?? ''));

        if ($dateRaw === '' || $amountRaw === '') {
            return null;
        }

        $date = $this->parseDate($dateRaw);
        $amount = $this->parseAmount($amountRaw);

        $description = $map['description'] !== null
            ? trim((string) ($data[$map['description']] ?? '')) ?: null
            : null;

        $reference = $map['reference'] !== null
            ? trim((string) ($data[$map['reference']] ?? '')) ?: null
            : null;

        return [
            'transaction_date' => $date,
            'description' => $description,
            'reference' => $reference,
            'amount' => $amount,
        ];
    }

    private function parseDate(string $value): string
    {
        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'd M Y', 'M d, Y'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateString();
            } catch (\Throwable) {
                // try next format
            }
        }

        return Carbon::parse($value)->toDateString();
    }

    private function parseAmount(string $value): float
    {
        $clean = str_replace([',', ' '], '', $value);
        $clean = str_replace(['RM', 'rm', '$'], '', $clean);

        if (! is_numeric($clean)) {
            throw new InvalidArgumentException("Invalid amount value: {$value}");
        }

        return round((float) $clean, 2);
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
