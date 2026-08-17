<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\CustomerStatementEmail;
use App\Models\Customer;
use App\Services\CustomerStatementService;
use App\Support\WalksTenants;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class SendMonthlyStatementsCommand extends Command
{
    use WalksTenants;

    protected $signature = 'statements:send-monthly
                            {--tenants=* : Limit to specific tenant ids}';

    protected $description = 'Email monthly statements to customers with send_statement enabled';

    public function handle(CustomerStatementService $statements): int
    {
        return $this->forEachTenant($this, function () use ($statements) {
            if (! Schema::hasColumn('customers', 'send_statement')) {
                $this->line('  send_statement column missing — skip');

                return;
            }
            $from = now()->subMonthNoOverflow()->startOfMonth();
            $to = now()->subMonthNoOverflow()->endOfMonth();
            $company = tenant()?->getCompanyDetails() ?? config('invoice.company');
            $sent = 0;

            Customer::query()
                ->where('send_statement', true)
                ->whereNotNull('email')
                ->chunkById(50, function ($customers) use ($statements, $from, $to, $company, &$sent) {
                    foreach ($customers as $customer) {
                        if (! filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
                            continue;
                        }
                        $statement = $statements->build($customer, $from, $to);
                        $pdfBytes = Pdf::loadView('pdf.customer_statement', compact('customer', 'statement', 'company'))
                            ->setPaper('a4', 'portrait')
                            ->output();
                        $filename = 'Statement-'.$customer->id.'-'.$from->toDateString().'.pdf';
                        Mail::to($customer->email)->send(new CustomerStatementEmail($customer, $statement, $company, $pdfBytes, $filename));
                        $sent++;
                    }
                });

            $this->line("  {$sent} statement(s) emailed");
        }, 'customers');
    }
}
