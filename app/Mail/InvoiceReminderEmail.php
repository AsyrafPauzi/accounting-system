<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceReminderEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public array $company,
        public string $downloadUrl,
        public int $offset,
    ) {}

    public function build()
    {
        $when = $this->offset === 0
            ? 'due today'
            : ($this->offset < 0
                ? 'due in '.abs($this->offset).' day(s)'
                : abs($this->offset).' day(s) overdue');

        return $this->subject('Reminder: invoice '.$this->invoice->invoice_number.' is '.$when)
            ->view('emails.invoice-reminder', [
                'invoice'      => $this->invoice,
                'customer'     => $this->invoice->customer,
                'company'      => $this->company,
                'download_url' => $this->downloadUrl,
                'offset'       => $this->offset,
                'when'         => $when,
            ]);
    }
}
