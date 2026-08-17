<?php

namespace App\Jobs;

use App\Mail\DeliveryOrderEmail;
use App\Models\DeliveryOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendDeliveryOrderEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $deliveryOrderId, public array $recipients, public array $company = []) {}

    public function handle(): void
    {
        $do = DeliveryOrder::with(['items', 'customer'])->findOrFail($this->deliveryOrderId);
        $company = $this->company ?: (tenant()?->getCompanyDetails() ?? config('invoice.company'));
        Mail::to($this->recipients)->send(new DeliveryOrderEmail($do, $company));
    }
}
