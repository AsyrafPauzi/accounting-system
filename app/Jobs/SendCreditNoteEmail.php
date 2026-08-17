<?php

namespace App\Jobs;

use App\Mail\CreditNoteEmail;
use App\Models\CreditNote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendCreditNoteEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $creditNoteId, public array $recipients, public array $company = []) {}

    public function handle(): void
    {
        $cn = CreditNote::with(['items', 'customer'])->findOrFail($this->creditNoteId);
        $company = $this->company ?: (tenant()?->getCompanyDetails() ?? config('invoice.company'));
        Mail::to($this->recipients)->send(new CreditNoteEmail($cn, $company));
        $cn->forceFill([
            'last_emailed_status' => 'sent',
            'last_emailed_at'     => now(),
            'last_emailed_error'  => null,
            'last_emailed_to'     => implode(',', $this->recipients),
        ])->save();
    }

    public function failed(\Throwable $exception): void
    {
        if ($cn = CreditNote::find($this->creditNoteId)) {
            $cn->forceFill([
                'last_emailed_status' => 'failed',
                'last_emailed_error'  => $exception->getMessage(),
            ])->save();
        }
    }
}
