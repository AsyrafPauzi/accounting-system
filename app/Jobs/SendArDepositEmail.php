<?php

namespace App\Jobs;

use App\Mail\ArDepositEmail;
use App\Models\ArDeposit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendArDepositEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $depositId, public array $recipients, public array $company = []) {}

    public function handle(): void
    {
        $deposit = ArDeposit::with(['customer'])->findOrFail($this->depositId);
        $company = $this->company ?: (tenant()?->getCompanyDetails() ?? config('invoice.company'));
        Mail::to($this->recipients)->send(new ArDepositEmail($deposit, $company));
    }
}
