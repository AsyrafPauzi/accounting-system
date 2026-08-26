<?php

namespace Tests\Feature\Payroll;

use App\Models\Employee;
use App\Models\PayrollEmployeeLine;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\EpfExportService;
use App\Services\PayrollService;
use App\Services\PcbExportService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeePayrollExportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private PayrollService $payroll;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);

        $this->tenant = $this->createTenantWithDatabase();
        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id'   => Plan::where('slug', 'corporate')->firstOrFail()->id,
            'status'    => 'active',
            'interval'  => 'lifetime',
            'gateway'   => 'system',
        ]);

        tenancy()->initialize($this->tenant);
        $this->payroll = app(PayrollService::class);
    }

    public function test_payroll_run_persists_employee_lines(): void
    {
        $employee = Employee::create([
            'employee_number' => 'EMP-001',
            'name'            => 'Ahmad Ali',
            'nric'            => '900101015432',
            'epf_number'      => '12345678',
            'tax_category'    => '1',
            'basic_salary'    => 5000,
            'is_active'       => true,
        ]);

        $journal = $this->payroll->record([
            'period_date'       => '2026-08-31',
            'bank_account_code' => '1200',
            'gross_salaries'    => 5000,
            'employer_epf'      => 650,
            'employer_socso'    => 0,
            'employer_eis'      => 0,
            'employer_hrd'      => 0,
            'epf_payable'       => 1200,
            'socso_payable'     => 0,
            'eis_payable'       => 0,
            'pcb_payable'       => 150,
            'hrd_payable'       => 0,
            'net_pay'           => 4300,
            'employee_lines'    => [[
                'employee_id'  => $employee->id,
                'gross_salary' => 5000,
                'employee_epf' => 550,
                'employer_epf' => 650,
                'pcb'          => 150,
                'net_pay'      => 4300,
            ]],
        ]);

        $this->assertDatabaseHas('payroll_employee_lines', [
            'journal_entry_id' => $journal->id,
            'employee_id'      => $employee->id,
            'gross_salary'     => 5000,
            'employee_epf'     => 550,
            'employer_epf'     => 650,
            'pcb'              => 150,
            'net_pay'          => 4300,
        ]);

        $this->assertSame(1, PayrollEmployeeLine::where('journal_entry_id', $journal->id)->count());
    }

    public function test_epf_csv_has_required_columns_and_employee_data(): void
    {
        $employee = Employee::create([
            'name'       => 'Siti Nur',
            'nric'       => '880808085678',
            'epf_number' => '87654321',
            'tax_category' => '2',
            'is_active'  => true,
        ]);

        $journal = $this->payroll->record($this->balancedPayload($employee));

        $csv = app(EpfExportService::class)->csvForJournal($journal, 'EPF-EMP-001');
        $lines = array_map('str_getcsv', explode("\n", trim($csv)));

        $this->assertSame(EpfExportService::HEADERS, $lines[0]);
        $this->assertSame('EPF-EMP-001', $lines[1][0]);
        $this->assertSame('87654321', $lines[1][1]);
        $this->assertSame('880808085678', $lines[1][2]);
        $this->assertSame('Siti Nur', $lines[1][3]);
        $this->assertSame('5000.00', $lines[1][4]);
        $this->assertSame('550.00', $lines[1][5]);
        $this->assertSame('650.00', $lines[1][6]);
    }

    public function test_pcb_csv_has_required_columns_and_employee_data(): void
    {
        $employee = Employee::create([
            'name'         => 'Lee Wei',
            'nric'         => '850505051234',
            'tax_category' => '3',
            'is_active'    => true,
        ]);

        $journal = $this->payroll->record($this->balancedPayload($employee, pcb: 200));

        $csv = app(PcbExportService::class)->csvForJournal($journal);
        $lines = array_map('str_getcsv', explode("\n", trim($csv)));

        $this->assertSame(PcbExportService::HEADERS, $lines[0]);
        $this->assertSame('Lee Wei', $lines[1][0]);
        $this->assertSame('850505051234', $lines[1][1]);
        $this->assertSame('3', $lines[1][2]);
        $this->assertSame('5000.00', $lines[1][3]);
        $this->assertSame('200.00', $lines[1][4]);
    }

    public function test_export_without_employee_lines_throws(): void
    {
        $journal = $this->payroll->record([
            'period_date'       => '2026-08-31',
            'bank_account_code' => '1200',
            'gross_salaries'    => 5000,
            'net_pay'           => 5000,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(EpfExportService::class)->csvForJournal($journal);
    }

    /**
     * @return array<string, mixed>
     */
    private function balancedPayload(Employee $employee, float $pcb = 150): array
    {
        $netPay = 5000 + 650 - 1200 - $pcb;

        return [
            'period_date'       => '2026-08-31',
            'bank_account_code' => '1200',
            'gross_salaries'    => 5000,
            'employer_epf'      => 650,
            'employer_socso'    => 0,
            'employer_eis'      => 0,
            'employer_hrd'      => 0,
            'epf_payable'       => 1200,
            'socso_payable'     => 0,
            'eis_payable'       => 0,
            'pcb_payable'       => $pcb,
            'hrd_payable'       => 0,
            'net_pay'           => $netPay,
            'employee_lines'    => [[
                'employee_id'  => $employee->id,
                'gross_salary' => 5000,
                'employee_epf' => 550,
                'employer_epf' => 650,
                'pcb'          => $pcb,
                'net_pay'      => 5000 - 550 - $pcb,
            ]],
        ];
    }
}
