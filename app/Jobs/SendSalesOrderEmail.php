<?php

namespace App\Jobs;

use App\Mail\SalesOrderEmail;
use App\Models\SalesOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendSalesOrderEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $salesOrderId, public array $recipients, public array $company = []) {}

    public function handle(): void
    {
        $so = SalesOrder::with(['items', 'customer'])->findOrFail($this->salesOrderId);
        $company = $this->company ?: (tenant()?->getCompanyDetails() ?? config('invoice.company'));
        Mail::to($this->recipients)->send(new SalesOrderEmail($so, $company));
    }
}
