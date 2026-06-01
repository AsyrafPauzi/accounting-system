<?php

namespace App\Console\Commands;

use App\Services\Ocr\OcrProviderResolver;
use App\Services\Ocr\OcrResult;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;

/**
 * Run the active OCR provider against every receipt under
 * tests/fixtures/receipts/, optionally compare against a sidecar
 * `<filename>.expected.json`, and print per-field accuracy.
 *
 * Usage:
 *   php artisan ocr:audit
 *   php artisan ocr:audit --filter=kedai
 *   php artisan ocr:audit --provider=tesseract
 */
class OcrAuditCommand extends Command
{
    protected $signature = 'ocr:audit
        {--filter= : Substring to match against fixture filenames}
        {--provider= : Override the configured provider (tesseract|gemini)}';

    protected $description = 'Run OCR against tests/fixtures/receipts/ and report per-field accuracy';

    /** Tolerance for money-field equality. */
    private const MONEY_TOLERANCE = 0.05;

    public function handle(OcrProviderResolver $resolver): int
    {
        $fixturesDir = base_path('tests/fixtures/receipts');
        if (! is_dir($fixturesDir)) {
            $this->error("Fixtures directory not found: $fixturesDir");
            return self::FAILURE;
        }

        if ($override = $this->option('provider')) {
            $this->info("Provider override: $override");
            // We override by temporarily mutating the settings row.
            $settings = \App\Models\OcrSettings::current();
            $original = $settings->provider;
            $settings->provider = $override;
            $settings->saveQuietly();
            try {
                return $this->runAudit($fixturesDir, $resolver);
            } finally {
                $settings->provider = $original;
                $settings->saveQuietly();
            }
        }

        return $this->runAudit($fixturesDir, $resolver);
    }

    private function runAudit(string $fixturesDir, OcrProviderResolver $resolver): int
    {
        $provider = $resolver->resolve();
        $filter = $this->option('filter');
        $files = $this->discoverFixtures($fixturesDir, $filter);

        if (empty($files)) {
            $this->warn('No fixtures found. Drop receipts into tests/fixtures/receipts/ then re-run.');
            return self::SUCCESS;
        }

        $this->info("Running OCR audit using provider: {$provider->name()}");
        $this->info('Fixtures: ' . count($files));
        $this->newLine();

        // Per-row results for the summary table.
        $rows = [];
        // Per-field hit/miss counters for the accuracy summary.
        $stats = [
            'vendor_name' => ['hit' => 0, 'tested' => 0],
            'bill_date' => ['hit' => 0, 'tested' => 0],
            'subtotal' => ['hit' => 0, 'tested' => 0],
            'tax_amount' => ['hit' => 0, 'tested' => 0],
            'total_amount' => ['hit' => 0, 'tested' => 0],
            'currency' => ['hit' => 0, 'tested' => 0],
            'reference' => ['hit' => 0, 'tested' => 0],
            'items_count' => ['hit' => 0, 'tested' => 0],
        ];

        foreach ($files as $absolutePath) {
            $filename = basename($absolutePath);
            $this->line("→ $filename");

            $relativePath = $this->toStorageRelative($absolutePath);

            $start = microtime(true);
            try {
                $result = $provider->extract($relativePath);
            } catch (\Throwable $e) {
                $this->error("  ERROR: " . $e->getMessage());
                $rows[] = [$filename, 'ERROR: ' . substr($e->getMessage(), 0, 50), '', '', '', '', '', ''];
                continue;
            }
            $elapsed = round((microtime(true) - $start) * 1000);

            if ($result->status === OcrResult::STATUS_FAILED) {
                $this->error("  FAILED: " . $result->error);
                $rows[] = [$filename, "FAIL: " . substr($result->error ?? '', 0, 40), '', '', '', '', '', ''];
                continue;
            }

            $expected = $this->loadExpected($absolutePath);
            $row = [
                $filename,
                $this->checkField($result->vendorName, $expected['vendor_name'] ?? null, $stats, 'vendor_name', false),
                $this->checkField($result->billDate, $expected['bill_date'] ?? null, $stats, 'bill_date', false),
                $this->checkField($result->subtotal, $expected['subtotal'] ?? null, $stats, 'subtotal', true),
                $this->checkField($result->taxAmount, $expected['tax_amount'] ?? null, $stats, 'tax_amount', true),
                $this->checkField($result->totalAmount, $expected['total_amount'] ?? null, $stats, 'total_amount', true),
                $this->checkField($result->currency, $expected['currency'] ?? null, $stats, 'currency', false),
                $this->checkItemsCount(count($result->items), $expected['items_count'] ?? null, $stats),
                $elapsed . 'ms',
            ];
            $rows[] = $row;

            if (! empty($result->warnings)) {
                foreach ($result->warnings as $w) $this->line("  ⚠ $w");
            }
        }

        $this->newLine();
        $table = new Table($this->getOutput());
        $table->setHeaders(['File', 'Vendor', 'Date', 'Subtotal', 'Tax', 'Total', 'Curr', 'Items#', 'Time']);
        $table->setRows($rows);
        $table->render();

        $this->newLine();
        $this->info('Per-field accuracy:');
        $this->printAccuracy($stats);

        return $this->overallPass($stats) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function discoverFixtures(string $dir, ?string $filter): array
    {
        $exts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        $found = [];
        foreach ($exts as $ext) {
            foreach (glob("$dir/*.$ext") as $file) {
                if ($filter && stripos($file, $filter) === false) continue;
                $found[] = $file;
            }
        }
        sort($found);
        return $found;
    }

    /**
     * Move the fixture path under storage/app/public so the existing
     * resolveAbsolutePath logic in TesseractProvider can find it. We use
     * absolute path (Tesseract provider tries that as the third fallback).
     */
    private function toStorageRelative(string $absolutePath): string
    {
        return $absolutePath;
    }

    private function loadExpected(string $fixturePath): ?array
    {
        $base = preg_replace('/\.(jpg|jpeg|png|webp|pdf)$/i', '', $fixturePath);
        $sidecar = $base . '.expected.json';
        if (! is_file($sidecar)) return null;
        $raw = file_get_contents($sidecar);
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private function checkField(mixed $actual, mixed $expected, array &$stats, string $key, bool $isMoney): string
    {
        if ($expected === null) {
            // No ground truth → display the actual value, mark as info-only.
            return $actual === null ? '—' : $this->shortDisplay($actual);
        }
        $stats[$key]['tested']++;

        $match = $isMoney
            ? ($actual !== null && abs((float) $actual - (float) $expected) <= self::MONEY_TOLERANCE)
            : ((string) $actual === (string) $expected);

        if ($match) $stats[$key]['hit']++;
        $marker = $match ? '<fg=green>✓</>' : '<fg=red>✗</>';
        return $marker . ' ' . $this->shortDisplay($actual);
    }

    private function checkItemsCount(int $actual, ?int $expected, array &$stats): string
    {
        if ($expected === null) return (string) $actual;
        $stats['items_count']['tested']++;
        $match = $actual === $expected;
        if ($match) $stats['items_count']['hit']++;
        $marker = $match ? '<fg=green>✓</>' : '<fg=red>✗</>';
        return "$marker $actual/$expected";
    }

    private function shortDisplay(mixed $v): string
    {
        if ($v === null) return '—';
        $s = is_float($v) ? number_format($v, 2) : (string) $v;
        return mb_strlen($s) > 20 ? mb_substr($s, 0, 17) . '…' : $s;
    }

    private function printAccuracy(array $stats): void
    {
        foreach ($stats as $field => $s) {
            if ($s['tested'] === 0) {
                $this->line(sprintf('  %-15s n/a (no ground truth)', $field));
                continue;
            }
            $pct = round($s['hit'] / $s['tested'] * 100);
            $color = $pct >= 85 ? 'green' : ($pct >= 60 ? 'yellow' : 'red');
            $this->line(sprintf("  %-15s <fg=%s>%d%% (%d/%d)</>", $field, $color, $pct, $s['hit'], $s['tested']));
        }
    }

    private function overallPass(array $stats): bool
    {
        foreach (['vendor_name', 'total_amount', 'bill_date'] as $criticalField) {
            $s = $stats[$criticalField];
            if ($s['tested'] === 0) continue;
            if ($s['hit'] / $s['tested'] < 0.85) return false;
        }
        return true;
    }
}
