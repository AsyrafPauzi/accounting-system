<?php

namespace App\Mail;

use App\Models\SalesOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SalesOrderEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SalesOrder $salesOrder, public array $company)
    {
        $this->salesOrder->loadMissing(['items', 'customer']);
    }

    public function build()
    {
        $so = $this->salesOrder;
        $pdf = Pdf::loadView('pdf.sales-document', [
            'title'      => 'Sales Order',
            'number'     => $so->so_number,
            'issue_date' => optional($so->issue_date)->toDateString() ?? $so->issue_date,
            'customer'   => $so->customer,
            'company'    => $this->company,
            'items'      => $so->items,
            'tax'        => $so->tax_amount,
            'total'      => $so->total_amount,
            'currency'   => $so->currency ?? 'MYR',
            'notes'      => $so->customer_notes,
            'qr_url'     => null,
        ])->output();

        return $this->from(config('mail.from.address'), $this->company['name'] ?? config('app.name'))
            ->subject('Sales order '.$so->so_number.' from '.($this->company['name'] ?? config('app.name')))
            ->view('emails.sales-document', [
                'doc_title'  => 'Sales Order',
                'doc_number' => $so->so_number,
                'customer'   => $so->customer,
                'company'    => $this->company,
                'amount'     => $so->total_amount,
                'currency'   => $so->currency ?? 'MYR',
            ])
            ->attachData($pdf, "Sales-Order-{$so->so_number}.pdf", ['mime' => 'application/pdf']);
    }
}
