<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\InvoiceReminderService;
use App\Support\WalksTenants;
use Illuminate\Console\Command;

class SendInvoiceRemindersCommand extends Command
{
    use WalksTenants;

    protected $signature = 'invoices:send-reminders
                            {--tenants=* : Limit to specific tenant ids}';

    protected $description = 'Email Wave-style invoice reminders (before / on / after due)';

    public function handle(InvoiceReminderService $reminders): int
    {
        return $this->forEachTenant($this, function () use ($reminders) {
            $n = $reminders->sendDueForTenant();
            $this->line("  {$n} reminder(s) sent");
        }, 'invoices');
    }
}
