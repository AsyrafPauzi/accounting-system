<?php

namespace App\Mail;

use App\Models\FirmInvitation;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ClientInviteFirm extends Mailable
{
    use Queueable, SerializesModels;

    public string $acceptUrl;

    public function __construct(
        public Tenant $tenant,
        public FirmInvitation $invitation,
        public ?string $inviterName = null,
    ) {
        $this->acceptUrl = route('firm.invite.accept', ['token' => $invitation->token]);
    }

    public function build()
    {
        $appName = config('app.name');
        $companyName = $this->tenant->display_name
            ?: ($this->tenant->legal_name ?: $this->tenant->id);

        return $this
            ->from(config('mail.from.address'), $appName)
            ->subject("{$companyName} invited you to manage their books on {$appName}")
            ->view('emails.client-invite-firm', [
                'appName' => $appName,
                'companyName' => $companyName,
                'inviterName' => $this->inviterName,
                'acceptUrl' => $this->acceptUrl,
                'expiresAt' => $this->invitation->expires_at,
            ]);
    }
}
