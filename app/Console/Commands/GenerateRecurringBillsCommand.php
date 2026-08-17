<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RecurringBill;
use App\Services\RecurringBillService;
use App\Support\WalksTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class GenerateRecurringBillsCommand extends Command
{
    use WalksTenants;

    protected $signature = 'bills:generate-recurring
                            {--tenants=* : Limit to specific tenant ids; omit for all tenants}
                            {--dry-run : Report what would be generated without creating bills}';

    protected $description = 'Generate bills from recurring-bill templates whose next_run_date <= today';

    public function handle(RecurringBillService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $generated = 0;

        return $this->forEachTenant($this, function () use ($service, $dryRun, &$generated) {
            if (! Schema::hasTable('recurring_bills')) {
                $this->line('  <comment>recurring_bills missing — skip</comment>');

                return;
            }
            $due = RecurringBill::query()->due()->count();
            if ($due === 0) {
                $this->line('  Nothing due.');

                return;
            }
            if ($dryRun) {
                $this->line("  <comment>[dry-run] {$due} template(s) due</comment>");

                return;
            }
            $n = $service->generateDue();
            $generated += $n;
            $this->line("  <fg=green>+{$n}</> bill(s) created");
        }, 'recurring_bills');
    }
}
