<?php

namespace App\Mail;

use App\Models\DebitNote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DebitNoteEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DebitNote $debitNote, public array $company)
    {
        $this->debitNote->loadMissing(['items', 'customer']);
    }

    public function build()
    {
        $dn = $this->debitNote;
        $pdf = Pdf::loadView('pdf.sales-document', [
            'title'      => 'Debit Note',
            'number'     => $dn->dn_number,
            'issue_date' => optional($dn->issue_date)->toDateString() ?? $dn->issue_date,
            'customer'   => $dn->customer,
            'company'    => $this->company,
            'items'      => $dn->items,
            'tax'        => $dn->tax_amount,
            'total'      => $dn->total_amount,
            'currency'   => $dn->currency ?? 'MYR',
            'notes'      => $dn->customer_notes,
            'qr_url'     => $dn->lhdn_qr_url,
        ])->output();

        return $this->from(config('mail.from.address'), $this->company['name'] ?? config('app.name'))
            ->subject('Debit note '.$dn->dn_number.' from '.($this->company['name'] ?? config('app.name')))
            ->view('emails.sales-document', [
                'doc_title'  => 'Debit Note',
                'doc_number' => $dn->dn_number,
                'customer'   => $dn->customer,
                'company'    => $this->company,
                'amount'     => $dn->total_amount,
                'currency'   => $dn->currency ?? 'MYR',
            ])
            ->attachData($pdf, "Debit-Note-{$dn->dn_number}.pdf", ['mime' => 'application/pdf']);
    }
}
