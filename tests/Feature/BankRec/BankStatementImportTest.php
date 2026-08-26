<?php

namespace Tests\Feature\BankRec;

use App\Models\Account;
use App\Models\BankStatement;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BankStatementImportService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BankStatementImportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Account $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);

        $this->tenant = $this->createTenantWithDatabase();
        $this->tenant->forceFill([
            'provision_status' => 'ready',
            'provisioned_at' => now(),
        ])->save();

        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => Plan::where('slug', 'growth')->firstOrFail()->id,
            'status' => 'active',
            'interval' => 'lifetime',
            'gateway' => 'system',
        ]);

        $adminRole = Role::where('name', 'admin')->first();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $adminRole?->id,
        ]);
        if ($adminRole) {
            $this->user->assignRole('admin');
        }

        tenancy()->initialize($this->tenant);

        $this->bankAccount = Account::query()
            ->where('code', '1200')
            ->firstOrFail();
    }

    public function test_import_three_line_csv_creates_statement_lines(): void
    {
        $csv = <<<'CSV'
date,description,amount
2026-08-01,Customer payment,150.00
2026-08-02,Supplier payment,-75.50
2026-08-03,Bank fee,-5.00
CSV;

        $result = app(BankStatementImportService::class)->importFromCsv(
            $csv,
            $this->bankAccount,
        );

        $this->assertSame(3, $result['line_count']);
        $this->assertInstanceOf(BankStatement::class, $result['statement']);
        $this->assertDatabaseCount('bank_statement_lines', 3);
        $this->assertSame('2026-08-01', $result['statement']->period_start->toDateString());
        $this->assertSame('2026-08-03', $result['statement']->period_end->toDateString());
    }

    public function test_import_endpoint_accepts_csv_upload(): void
    {
        $csv = <<<'CSV'
date,description,amount
2026-08-10,Rental received,500.00
2026-08-11,Utilities,-120.00
2026-08-12,Refund,20.00
CSV;

        $file = UploadedFile::fake()->createWithContent('statement.csv', $csv);

        $response = $this->actingAs($this->user)->post(route('bank-rec.import.store'), [
            'account_id' => $this->bankAccount->id,
            'file' => $file,
            'opening_balance' => 1000,
            'closing_balance' => 1400,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('bank_statements', 1);
        $this->assertDatabaseCount('bank_statement_lines', 3);
    }

    public function test_import_endpoint_accepts_pdf_upload(): void
    {
        $text = <<<'TXT'
2026-08-10 Rental received 500.00
2026-08-11 Utilities -120.00
TXT;

        $pdfPath = sys_get_temp_dir().'/bank-upload-test.pdf';
        $this->writeMinimalTextPdf($pdfPath, $text);

        try {
            $file = new \Illuminate\Http\UploadedFile($pdfPath, 'statement.pdf', 'application/pdf', null, true);

            $response = $this->actingAs($this->user)->post(route('bank-rec.import.store'), [
                'account_id' => $this->bankAccount->id,
                'file' => $file,
            ]);

            $response->assertRedirect();
            $this->assertDatabaseHas('bank_statements', ['source' => 'pdf']);
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
