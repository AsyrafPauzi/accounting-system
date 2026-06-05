<?php

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class CustomerStatementEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public array $statement,
        public array $company,
        public string $pdfBytes,
        public string $pdfFilename,
    ) {}

    public function build()
    {
        $companyName = $this->company['name'] ?? config('app.name');
        $subject = "Account statement from {$companyName} ({$this->statement['from']} to {$this->statement['to']})";

        return $this
            ->from(config('mail.from.address'), $companyName)
            ->subject($subject)
            ->view('emails.customer_statement', [
                'customer'  => $this->customer,
                'statement' => $this->statement,
                'company'   => $this->company,
            ])
            ->attachData($this->pdfBytes, $this->pdfFilename, ['mime' => 'application/pdf']);
    }
}
