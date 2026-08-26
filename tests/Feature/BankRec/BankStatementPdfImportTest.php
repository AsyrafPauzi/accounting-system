<?php

namespace Tests\Feature\BankRec;

use App\Models\Account;
use App\Services\BankStatementImportService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankStatementPdfImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);

        $tenant = $this->createTenantWithDatabase();
        tenancy()->initialize($tenant);
    }

    public function test_parse_statement_text_extracts_dated_lines(): void
    {
        $text = <<<'TXT'
2026-08-01 Customer payment 150.00
2026-08-02 Supplier payment (75.50)
01/08/2026 Bank fee -5.00
TXT;

        $rows = app(BankStatementImportService::class)->parseStatementText($text);

        $this->assertCount(3, $rows);
        $this->assertSame('2026-08-01', $rows[0]['transaction_date']);
        $this->assertSame('Customer payment', $rows[0]['description']);
        $this->assertSame(150.0, $rows[0]['amount']);
        $this->assertSame(-75.5, $rows[1]['amount']);
        $this->assertSame(-5.0, $rows[2]['amount']);
    }

    public function test_import_from_pdf_path_uses_text_extraction(): void
    {
        $account = Account::query()->where('code', '1200')->firstOrFail();
        $pdfPath = sys_get_temp_dir().'/bank-statement-test.pdf';
        $this->writeMinimalTextPdf($pdfPath, "2026-08-10 Rental received 500.00\n2026-08-11 Utilities -120.00\n");

        try {
            $result = app(BankStatementImportService::class)->importFromPdf($pdfPath, $account);
            $this->assertGreaterThanOrEqual(1, $result['line_count']);
            $this->assertSame('pdf', $result['statement']->source);
        } finally {
            @unlink($pdfPath);
        }
    }

    private function writeMinimalTextPdf(string $path, string $text): void
    {
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $content = "BT /F1 12 Tf 50 750 Td ({$escaped}) Tj ET";
        $objects = [
            "1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj\n",
            "2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj\n",
            "3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>endobj\n",
            "4 0 obj<< /Length ".strlen($content)." >>stream\n{$content}\nendstream\nendobj\n",
            "5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        file_put_contents($path, $pdf);
    }
}
