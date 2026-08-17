<?php

namespace App\Jobs;

use App\Mail\DebitNoteEmail;
use App\Models\DebitNote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendDebitNoteEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $debitNoteId, public array $recipients, public array $company = []) {}

    public function handle(): void
    {
        $dn = DebitNote::with(['items', 'customer'])->findOrFail($this->debitNoteId);
        $company = $this->company ?: (tenant()?->getCompanyDetails() ?? config('invoice.company'));
        Mail::to($this->recipients)->send(new DebitNoteEmail($dn, $company));
    }
}
