<?php

namespace Tests\Feature\BankRec;

use App\Models\Account;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BankReconciliationService;
use App\Services\BankStatementImportService;
use App\Support\AccountingPeriodResolver;
use App\Support\JournalWriter;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuggestMatchTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Account $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlanSeeder::class);

        $this->tenant = $this->createTenantWithDatabase();
        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => Plan::where('slug', 'growth')->firstOrFail()->id,
            'status' => 'active',
            'interval' => 'lifetime',
            'gateway' => 'system',
        ]);

        tenancy()->initialize($this->tenant);
        AccountingPeriodResolver::ensurePeriodsExist();

        $this->bankAccount = Account::query()->where('code', '1200')->firstOrFail();
    }

    public function test_suggest_match_by_amount_within_three_days(): void
    {
        $txnDate = now()->startOfMonth()->addDays(5)->toDateString();
        $statementDate = now()->startOfMonth()->addDays(7)->toDateString();

        $journalId = JournalWriter::postSystem(
            [
                'date' => $txnDate,
                'description' => 'Customer payment INV-1001',
                'reference_type' => 'Manual',
            ],
            [
                ['account_code' => '1200', 'debit' => 250.00, 'credit' => 0, 'account_id' => $this->bankAccount->id],
                ['account_code' => '4000', 'debit' => 0, 'credit' => 250.00],
            ],
        );

        $journalItemId = (int) \DB::table('journal_items')
            ->where('journal_entry_id', $journalId)
            ->where('account_id', $this->bankAccount->id)
            ->value('id');

        $csv = "date,description,amount\n{$statementDate},Payment received,250.00\n";
        $import = app(BankStatementImportService::class)->importFromCsv($csv, $this->bankAccount);
        $statement = $import['statement'];

        $suggested = app(BankReconciliationService::class)->suggestMatches($statement);

        $this->assertSame(1, $suggested);

        $line = BankStatementLine::query()->where('bank_statement_id', $statement->id)->firstOrFail();
        $this->assertSame('suggested', $line->match_status);
        $this->assertSame($journalItemId, (int) $line->matched_journal_item_id);
        $this->assertGreaterThan(0.5, (float) $line->match_confidence);
    }

    public function test_confirm_match_respects_closed_period(): void
    {
        $closedDate = now()->startOfMonth()->addDays(3)->toDateString();

        JournalWriter::postSystem(
            ['date' => $closedDate, 'description' => 'Old deposit', 'reference_type' => 'Manual'],
            [
                ['account_code' => '1200', 'debit' => 100.00, 'credit' => 0, 'account_id' => $this->bankAccount->id],
                ['account_code' => '4000', 'debit' => 0, 'credit' => 100.00],
            ],
        );

        $csv = "date,description,amount\n{$closedDate},Deposit,100.00\n";
        $statement = app(BankStatementImportService::class)->importFromCsv($csv, $this->bankAccount)['statement'];
        app(BankReconciliationService::class)->suggestMatches($statement);

        $line = BankStatementLine::query()->where('bank_statement_id', $statement->id)->firstOrFail();

        \App\Models\AccountingPeriod::query()
            ->whereDate('start_date', '<=', $closedDate)
            ->whereDate('end_date', '>=', $closedDate)
            ->update(['status' => 'closed', 'closed_at' => now()]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('closed');

        app(BankReconciliationService::class)->confirmMatch($line, (int) $line->matched_journal_item_id);
    }

    public function test_amount_outside_date_window_is_not_suggested(): void
    {
        $txnDate = now()->startOfMonth()->toDateString();
        $statementDate = now()->startOfMonth()->addDays(10)->toDateString();

        JournalWriter::postSystem(
            ['date' => $txnDate, 'description' => 'Too early', 'reference_type' => 'Manual'],
            [
                ['account_code' => '1200', 'debit' => 88.00, 'credit' => 0, 'account_id' => $this->bankAccount->id],
                ['account_code' => '4000', 'debit' => 0, 'credit' => 88.00],
            ],
        );

        $csv = "date,description,amount\n{$statementDate},Late payment,88.00\n";
        $statement = app(BankStatementImportService::class)->importFromCsv($csv, $this->bankAccount)['statement'];

        $suggested = app(BankReconciliationService::class)->suggestMatches($statement);

        $this->assertSame(0, $suggested);
        $this->assertSame('unmatched', BankStatementLine::query()->where('bank_statement_id', $statement->id)->value('match_status'));
    }
}
