<?php

namespace App\Mail;

use App\Models\ArDeposit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ArDepositEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ArDeposit $deposit, public array $company)
    {
        $this->deposit->loadMissing(['customer']);
    }

    public function build()
    {
        $deposit = $this->deposit;
        $number = $deposit->reference ?: ('DEP-'.$deposit->id);
        $items = new Collection([
            (object) [
                'description' => 'Customer deposit / receipt',
                'quantity'    => 1,
                'unit_price'  => $deposit->amount,
                'amount'      => $deposit->amount,
            ],
        ]);

        $pdf = Pdf::loadView('pdf.sales-document', [
            'title'      => 'Customer Receipt',
            'number'     => $number,
            'issue_date' => optional($deposit->payment_date)->toDateString() ?? $deposit->payment_date,
            'customer'   => $deposit->customer,
            'company'    => $this->company,
            'items'      => $items,
            'tax'        => 0,
            'total'      => $deposit->amount,
            'currency'   => 'MYR',
            'notes'      => $deposit->notes,
            'qr_url'     => null,
        ])->output();

        return $this->from(config('mail.from.address'), $this->company['name'] ?? config('app.name'))
            ->subject('Receipt '.$number.' from '.($this->company['name'] ?? config('app.name')))
            ->view('emails.sales-document', [
                'doc_title'  => 'Customer Receipt',
                'doc_number' => $number,
                'customer'   => $deposit->customer,
                'company'    => $this->company,
                'amount'     => $deposit->amount,
                'currency'   => 'MYR',
            ])
            ->attachData($pdf, "Receipt-{$number}.pdf", ['mime' => 'application/pdf']);
    }
}
