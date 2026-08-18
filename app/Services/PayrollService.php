<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Posts a single payroll run as a balanced journal entry.
 *
 * Designed for tenants who calculate payroll outside this system (in
 * a dedicated payroll app or spreadsheet) and just need to record the
 * accounting impact. The caller passes raw RM amounts; this service
 * never recomputes percentages.
 *
 * Will auto-create any missing payroll-related Chart of Accounts rows
 * the first time it runs, so tenants don't have to manually set them
 * up before their first payroll.
 */
class PayrollService
{
    /**
     * Default account codes used by the payroll posting. Stable codes
     * keep the journal narrative readable on Trial Balance / GL.
     *
     * @var array<string,array{code:string,name:string,type:string,sub_type?:string|null,description:string}>
     */
    public const PAYROLL_ACCOUNTS = [
        // Expenses (debit side)
        'salaries_expense' => ['code' => '5100', 'name' => 'Salaries & Wages Expense', 'type' => 'expense', 'description' => 'Gross pay before deductions.'],
        'epf_expense'      => ['code' => '5110', 'name' => 'EPF Expense (Employer)',    'type' => 'expense', 'description' => 'Employer share of EPF / KWSP contributions.'],
        'socso_expense'    => ['code' => '5120', 'name' => 'SOCSO Expense (Employer)',  'type' => 'expense', 'description' => 'Employer share of SOCSO / PERKESO contributions.'],
        'eis_expense'      => ['code' => '5130', 'name' => 'EIS Expense (Employer)',    'type' => 'expense', 'description' => 'Employer share of EIS contributions.'],
        'hrd_expense'      => ['code' => '5140', 'name' => 'HRD Levy Expense',          'type' => 'expense', 'description' => 'Human Resource Development Fund levy (1% of payroll, if registered).'],

        // Liabilities (credit side — money withheld pending remittance)
        'epf_payable'   => ['code' => '2200', 'name' => 'EPF Payable',         'type' => 'liability', 'description' => 'Combined employee + employer EPF owed to KWSP.'],
        'socso_payable' => ['code' => '2210', 'name' => 'SOCSO Payable',       'type' => 'liability', 'description' => 'Combined SOCSO owed to PERKESO.'],
        'eis_payable'   => ['code' => '2220', 'name' => 'EIS Payable',         'type' => 'liability', 'description' => 'Combined EIS owed to PERKESO.'],
        'pcb_payable'   => ['code' => '2230', 'name' => 'PCB Payable (LHDN)',  'type' => 'liability', 'description' => 'Monthly PCB / income tax withheld, owed to LHDN.'],
        'hrd_payable'   => ['code' => '2240', 'name' => 'HRD Levy Payable',    'type' => 'liability', 'description' => 'HRDF levy owed.'],
    ];

    /**
     * Create-or-fetch every payroll account this service needs. Idempotent.
     *
     * @return array<string,Account> Keyed by the same keys as PAYROLL_ACCOUNTS.
     */
    public function ensureAccounts(): array
    {
        $resolved = [];
        $orderBase = (int) (Account::max('display_order') ?? 0);
        $i = 0;

        foreach (self::PAYROLL_ACCOUNTS as $key => $defaults) {
            $accountAtCode = Account::where('code', $defaults['code'])->first();
            if ($accountAtCode
                && $accountAtCode->name === $defaults['name']
                && $accountAtCode->type === $defaults['type']) {
                $resolved[$key] = $accountAtCode;
                continue;
            }

            $accountByName = Account::where('name', $defaults['name'])
                ->where('type', $defaults['type'])
                ->first();
            if ($accountByName) {
                $resolved[$key] = $accountByName;
                continue;
            }

            $code = $accountAtCode
                ? $this->nextAvailableCode($defaults['code'])
                : $defaults['code'];

            $resolved[$key] = Account::create([
                'code'          => $code,
                'name'          => $defaults['name'],
                'type'          => $defaults['type'],
                'sub_type'      => $defaults['sub_type'] ?? null,
                'description'   => $defaults['description'],
                'is_active'     => true,
                'display_order' => $orderBase + (++$i),
            ]);
        }

        return $resolved;
    }

    private function nextAvailableCode(string $preferredCode): string
    {
        $width = strlen($preferredCode);
        $candidate = (int) $preferredCode;

        do {
            $candidate++;
            $code = str_pad((string) $candidate, $width, '0', STR_PAD_LEFT);
        } while (Account::where('code', $code)->exists());

        return $code;
    }

    /**
     * Post the payroll journal entry.
     *
     * The caller passes the raw RM amounts they have already calculated in
     * their payroll system. We do not redo any percentage math; we only
     * verify that the resulting debits equal credits (double-entry safety).
     *
     * @param array{
     *     period_date:string,
     *     description:?string,
     *     reference_number:?string,
     *     bank_account_code:string,
     *     gross_salaries:float|int|string,
     *     employer_epf:float|int|string,
     *     employer_socso:float|int|string,
     *     employer_eis:float|int|string,
     *     employer_hrd:float|int|string,
     *     epf_payable:float|int|string,
     *     socso_payable:float|int|string,
     *     eis_payable:float|int|string,
     *     pcb_payable:float|int|string,
     *     hrd_payable:float|int|string,
     *     net_pay:float|int|string,
     * } $data
     */
    public function record(array $data): JournalEntry
    {
        $accounts = $this->ensureAccounts();

        $bank = Account::where('code', $data['bank_account_code'])->firstOrFail();

        // Coerce all money fields to floats. Empty / null → 0.
        $f = static fn ($v) => round((float) ($v ?? 0), 2);

        $gross  = $f($data['gross_salaries']);
        $eEpf   = $f($data['employer_epf']);
        $eSocso = $f($data['employer_socso']);
        $eEis   = $f($data['employer_eis']);
        $eHrd   = $f($data['employer_hrd']);

        $epfP   = $f($data['epf_payable']);
        $socsoP = $f($data['socso_payable']);
        $eisP   = $f($data['eis_payable']);
        $pcbP   = $f($data['pcb_payable']);
        $hrdP   = $f($data['hrd_payable']);

        $netPay = $f($data['net_pay']);

        $totalDebits  = round($gross + $eEpf + $eSocso + $eEis + $eHrd, 2);
        $totalCredits = round($epfP + $socsoP + $eisP + $pcbP + $hrdP + $netPay, 2);

        // Round-off safety: allow up to 1 sen drift, reject anything else.
        if (abs($totalDebits - $totalCredits) > 0.01) {
            throw new InvalidArgumentException(
                "Payroll entry is unbalanced. Debits = RM " . number_format($totalDebits, 2) .
                ", Credits = RM " . number_format($totalCredits, 2) . "."
            );
        }

        if ($gross <= 0) {
            throw new InvalidArgumentException('Gross salaries must be greater than zero.');
        }

        $period      = $data['period_date'];
        $description = $data['description'] ?: 'Payroll for ' . date('F Y', strtotime($period));
        $reference   = $data['reference_number'] ?: 'PAY-' . date('Y-m', strtotime($period));

        return DB::transaction(function () use (
            $period, $description, $reference, $accounts, $bank,
            $gross, $eEpf, $eSocso, $eEis, $eHrd,
            $epfP, $socsoP, $eisP, $pcbP, $hrdP, $netPay
        ) {
            $journal = JournalEntry::create([
                'date'             => $period,
                'description'      => $description,
                'reference_number' => $reference,
                'type'             => 'manual',
                'status'           => 'posted',
            ]);

            // Each row built only if non-zero — keeps the journal clean
            // for tenants who don't pay HRD, etc.
            $rows = [];
            $push = static function (Account $account, float $debit, float $credit, string $label) use (&$rows): void {
                if ($debit == 0.0 && $credit == 0.0) {
                    return;
                }
                $rows[] = [
                    'account'     => $account,
                    'debit'       => $debit,
                    'credit'      => $credit,
                    'description' => $label,
                ];
            };

            // Debits — what payroll cost us
            $push($accounts['salaries_expense'], $gross,  0, 'Gross salaries & wages');
            $push($accounts['epf_expense'],      $eEpf,   0, 'Employer EPF contribution');
            $push($accounts['socso_expense'],    $eSocso, 0, 'Employer SOCSO contribution');
            $push($accounts['eis_expense'],      $eEis,   0, 'Employer EIS contribution');
            $push($accounts['hrd_expense'],      $eHrd,   0, 'HRD Levy');

            // Credits — what we owe (statutory) and what left the bank (net pay)
            $push($accounts['epf_payable'],   0, $epfP,   'EPF withheld (employee + employer)');
            $push($accounts['socso_payable'], 0, $socsoP, 'SOCSO withheld (employee + employer)');
            $push($accounts['eis_payable'],   0, $eisP,   'EIS withheld (employee + employer)');
            $push($accounts['pcb_payable'],   0, $pcbP,   'PCB withheld');
            $push($accounts['hrd_payable'],   0, $hrdP,   'HRD Levy payable');
            $push($bank,                      0, $netPay, 'Net pay to employees');

            foreach ($rows as $row) {
                JournalItem::create([
                    'journal_entry_id' => $journal->id,
                    'account_id'       => $row['account']->id,
                    'account_code'     => $row['account']->code,
                    'debit'            => $row['debit'],
                    'credit'           => $row['credit'],
                    'description'      => $row['description'],
                ]);
            }

            return $journal;
        });
    }

    /**
     * Post several payroll runs in one transaction.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<JournalEntry>
     */
    public function recordMany(array $rows): array
    {
        return DB::transaction(function () use ($rows) {
            $journals = [];
            foreach ($rows as $row) {
                $journals[] = $this->record($row);
            }

            return $journals;
        });
    }
}
