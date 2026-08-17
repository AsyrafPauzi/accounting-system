<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PurchaseOrder $order, public array $company)
    {
        $this->order->loadMissing(['items', 'supplier']);
    }

    public function build()
    {
        $po = $this->order;
        $pdf = Pdf::loadView('pdf.sales-document', [
            'title'      => 'Purchase Order',
            'number'     => $po->po_number,
            'issue_date' => optional($po->issue_date)->toDateString() ?? $po->issue_date,
            'customer'   => $po->supplier,
            'company'    => $this->company,
            'items'      => $po->items,
            'tax'        => $po->tax_amount,
            'total'      => $po->total_amount,
            'currency'   => $po->currency ?? 'MYR',
            'notes'      => $po->notes,
            'qr_url'     => null,
        ])->output();

        return $this->from(config('mail.from.address'), $this->company['name'] ?? config('app.name'))
            ->subject('Purchase order '.$po->po_number.' from '.($this->company['name'] ?? config('app.name')))
            ->view('emails.sales-document', [
                'doc_title'  => 'Purchase Order',
                'doc_number' => $po->po_number,
                'customer'   => $po->supplier,
                'company'    => $this->company,
                'amount'     => $po->total_amount,
                'currency'   => $po->currency ?? 'MYR',
            ])
            ->attachData($pdf, "Purchase-Order-{$po->po_number}.pdf", ['mime' => 'application/pdf']);
    }
}
