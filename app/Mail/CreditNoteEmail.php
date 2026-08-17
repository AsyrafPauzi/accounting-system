<?php

namespace App\Mail;

use App\Models\CreditNote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class CreditNoteEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CreditNote $creditNote, public array $company)
    {
        $this->creditNote->loadMissing(['items', 'customer']);
    }

    public function build()
    {
        $downloadUrl = URL::temporarySignedRoute(
            'public.credit-notes.download',
            now()->addDays(30),
            [
                'uuid'      => $this->creditNote->uuid,
                'tenant_id' => function_exists('tenant') && tenant() ? tenant('id') : null,
            ]
        );

        return $this->from(config('mail.from.address'), $this->company['name'] ?? config('app.name'))
            ->subject('Credit note '.$this->creditNote->cn_number.' from '.($this->company['name'] ?? config('app.name')))
            ->view('emails.credit-note', [
                'creditNote'   => $this->creditNote,
                'customer'     => $this->creditNote->customer,
                'company'      => $this->company,
                'download_url' => $downloadUrl,
            ]);
    }
}
