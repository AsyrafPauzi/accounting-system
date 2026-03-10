<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceEmail extends Mailable implements ShouldQueue
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

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'customer' => $customer,
            'company' => $company,
        ])->setPaper('a4', 'portrait');

        return $this->subject($subject)
            ->view('emails.invoice', [
                'invoice' => $invoice,
                'customer' => $customer,
                'company' => $company,
            ])
            ->attachData(
                $pdf->output(),
                "Invoice-{$invoice->invoice_number}.pdf",
                ['mime' => 'application/pdf']
            );
    }
}

