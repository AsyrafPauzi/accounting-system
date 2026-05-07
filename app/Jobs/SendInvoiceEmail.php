<?php

namespace App\Jobs;

use App\Mail\InvoiceEmail;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendInvoiceEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $invoiceId;
    public array $recipients;
    public array $company;

    /**
     * Create a new job instance.
     */
    public function __construct(int $invoiceId, array $recipients, array $company = [])
    {
        $this->invoiceId = $invoiceId;
        $this->recipients = $recipients;
        $this->company = $company;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $invoice = Invoice::with(['items', 'customer'])->findOrFail($this->invoiceId);
        
        $company = $this->company;
        
        if (empty($company)) {
            $company = config('invoice.company');
            if (function_exists('tenant') && tenant()) {
                $company = tenant()->getCompanyDetails();
            }
        }

        Mail::to($this->recipients)->send(new InvoiceEmail($invoice, $company));

        $invoice->forceFill([
            'last_emailed_status' => 'sent',
            'last_emailed_at' => now(),
            'last_emailed_error' => null,
            'last_emailed_to' => implode(',', $this->recipients),
        ])->save();
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        if ($invoice = Invoice::find($this->invoiceId)) {
            $invoice->forceFill([
                'last_emailed_status' => 'failed',
                'last_emailed_error' => $exception->getMessage(),
            ])->save();
        }

        Log::error('Failed to send invoice email', [
            'invoice_id' => $this->invoiceId,
            'error' => $exception->getMessage(),
        ]);
    }
}

