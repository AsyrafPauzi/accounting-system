<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceEmail extends Mailable
{
    use Queueable, SerializesModels;

    public Invoice $invoice;
    public array $company;

    /**
     * Create a new message instance.
     */
    public function __construct(Invoice $invoice, array $company)
    {
        $this->invoice = $invoice->loadMissing(['items', 'customer']);
        $this->company = $company;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $invoice = $this->invoice;
        $customer = $invoice->customer;
        $company = $this->company;

        $subjectFormat = config('invoice.email.subject_format', 'Invoice :number from :company');
        $subject = strtr($subjectFormat, [
            ':number' => $invoice->invoice_number,
            ':company' => $company['name'] ?? config('app.name'),
        ]);

        $tenantId = function_exists('tenant') && tenant() ? tenant('id') : auth()->user()?->tenant_id;
        $signedParams = ['uuid' => $invoice->uuid, 'tenant_id' => $tenantId];

        $viewPayUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'public.invoices.show',
            now()->addDays(30),
            $signedParams
        );

        $downloadUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'public.invoices.download',
            now()->addDays(30),
            $signedParams
        );

        $pixelUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'public.invoices.pixel',
            now()->addDays(30),
            $signedParams
        );

        return $this->from(config('mail.from.address'), $company['name'] ?? config('app.name'))
            ->subject($subject)
            ->view('emails.invoice', [
                'invoice' => $invoice,
                'customer' => $customer,
                'company' => $company,
                'view_pay_url' => $viewPayUrl,
                'download_url' => $downloadUrl,
                'pixel_url' => $pixelUrl,
            ]);
    }
}
