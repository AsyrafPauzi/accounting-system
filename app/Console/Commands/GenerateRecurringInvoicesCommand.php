<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\RecurringInvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Daily cron entry point. Loops every tenant, asks the recurring-invoice
 * service to materialise drafts for whichever templates are due, and prints
 * a summary.
 *
 * Generated invoices are always DRAFT. Templates with auto_email also
 * queue the PDF to the customer.
 */
class GenerateRecurringInvoicesCommand extends Command
{
    protected $signature = 'invoices:generate-recurring
                            {--tenants=* : Limit to specific tenant ids; omit for all tenants}
                            {--dry-run : Report what would be generated without creating any invoices}';

    protected $description = 'Generate draft invoices from recurring-invoice templates whose next_run_date <= today';

    public function handle(RecurringInvoiceService $service): int
    {
        $ids = $this->option('tenants');
        $dryRun = (bool) $this->option('dry-run');

        $tenants = $ids
            ? Tenant::query()->whereIn('id', $ids)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants matched. Use php artisan tenants:list to see ids.');
            return self::FAILURE;
        }

        $totalGenerated = 0;
        $totalDue = 0;
        $errors = 0;

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");
            tenancy()->initialize($tenant);

            try {
                if (! Schema::hasTable('recurring_invoices')) {
                    $this->line('  <comment>recurring_invoices table missing — skip (run tenants:migrate first)</comment>');
                    continue;
                }

                $due = \App\Models\RecurringInvoice::query()->due()->count();
                $totalDue += $due;

                if ($due === 0) {
                    $this->line('  Nothing due.');
                    continue;
                }

                if ($dryRun) {
                    $this->line("  <comment>[dry-run] {$due} template(s) due — would generate drafts</comment>");
                    continue;
                }

                $generated = $service->generateDue();
                $totalGenerated += $generated;

                $this->line("  <fg=green>+{$generated}</> draft invoice(s) created");
            } catch (\Throwable $e) {
                $errors++;
                $this->error("  Failed: {$e->getMessage()}");
            } finally {
                tenancy()->end();
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("Dry-run complete. {$totalDue} template(s) due across {$tenants->count()} tenant(s).");
        } else {
            $this->info("Done. Generated {$totalGenerated} draft invoice(s) across {$tenants->count()} tenant(s)." . ($errors > 0 ? " ({$errors} tenant errors — check logs)" : ''));
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
