<?php

namespace App\Jobs;

use App\Mail\PurchaseOrderEmail;
use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPurchaseOrderEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $purchaseOrderId, public array $recipients, public array $company = []) {}

    public function handle(): void
    {
        $po = PurchaseOrder::with(['items', 'supplier'])->findOrFail($this->purchaseOrderId);
        $company = $this->company ?: (tenant()?->getCompanyDetails() ?? config('invoice.company'));
        Mail::to($this->recipients)->send(new PurchaseOrderEmail($po, $company));
    }
}
