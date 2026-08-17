<?php

namespace App\Mail;

use App\Models\DeliveryOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeliveryOrderEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DeliveryOrder $deliveryOrder, public array $company)
    {
        $this->deliveryOrder->loadMissing(['items', 'customer', 'salesOrder.items']);
    }

    public function build()
    {
        $do = $this->deliveryOrder;
        $items = $do->pdfLineItems();
        $tax = round($items->sum(fn ($i) => $i->amount * $i->tax_rate / 100), 2);
        $total = round($items->sum('amount') + $tax, 2);
        $pdf = Pdf::loadView('pdf.sales-document', [
            'title'      => 'Delivery Order',
            'number'     => $do->do_number,
            'issue_date' => optional($do->issue_date)->toDateString() ?? $do->issue_date,
            'customer'   => $do->customer,
            'company'    => $this->company,
            'items'      => $items,
            'tax'        => $tax,
            'total'      => $total,
            'currency'   => $do->currency ?? 'MYR',
            'notes'      => $do->customer_notes,
            'qr_url'     => null,
        ])->output();

        return $this->from(config('mail.from.address'), $this->company['name'] ?? config('app.name'))
            ->subject('Delivery order '.$do->do_number.' from '.($this->company['name'] ?? config('app.name')))
            ->view('emails.sales-document', [
                'doc_title'  => 'Delivery Order',
                'doc_number' => $do->do_number,
                'customer'   => $do->customer,
                'company'    => $this->company,
            ])
            ->attachData($pdf, "Delivery-Order-{$do->do_number}.pdf", ['mime' => 'application/pdf']);
    }
}
