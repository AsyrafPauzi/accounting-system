<?php

namespace App\Mail;

use App\Models\GoodsReceipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GoodsReceiptEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public GoodsReceipt $order, public array $company)
    {
        $this->order->loadMissing(['items', 'supplier']);
    }

    public function build()
    {
        $grn = $this->order;
        $pdf = Pdf::loadView('pdf.sales-document', [
            'title'      => 'Goods Receipt',
            'number'     => $grn->grn_number,
            'issue_date' => optional($grn->issue_date)->toDateString() ?? $grn->issue_date,
            'customer'   => $grn->supplier,
            'company'    => $this->company,
            'items'      => $grn->items,
            'tax'        => 0,
            'total'      => $grn->items->sum('quantity'),
            'currency'   => $grn->currency ?? 'MYR',
            'notes'      => $grn->notes,
            'qr_url'     => null,
        ])->output();

        return $this->from(config('mail.from.address'), $this->company['name'] ?? config('app.name'))
            ->subject('Goods receipt '.$grn->grn_number.' from '.($this->company['name'] ?? config('app.name')))
            ->view('emails.sales-document', [
                'doc_title'  => 'Goods Receipt',
                'doc_number' => $grn->grn_number,
                'customer'   => $grn->supplier,
                'company'    => $this->company,
                'amount'     => $grn->items->sum('quantity'),
                'currency'   => $grn->currency ?? 'MYR',
            ])
            ->attachData($pdf, "Goods-Receipt-{$grn->grn_number}.pdf", ['mime' => 'application/pdf']);
    }
}
