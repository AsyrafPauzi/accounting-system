<?php

namespace App\Jobs;

use App\Mail\GoodsReceiptEmail;
use App\Models\GoodsReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendGoodsReceiptEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $goodsReceiptId, public array $recipients, public array $company = []) {}

    public function handle(): void
    {
        $grn = GoodsReceipt::with(['items', 'supplier'])->findOrFail($this->goodsReceiptId);
        $company = $this->company ?: (tenant()?->getCompanyDetails() ?? config('invoice.company'));
        Mail::to($this->recipients)->send(new GoodsReceiptEmail($grn, $company));
    }
}
