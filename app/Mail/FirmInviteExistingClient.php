<?php

namespace App\Mail;

use App\Models\Firm;
use App\Models\FirmInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email an existing SME owner when their accountancy firm requests
 * to manage their books on BukuCloud.
 *
 * The email:
 *   - Identifies the firm by name and the inviter's name (so the
 *     recipient can verify it's a real handshake, not phishing).
 *   - Links them to /settings/invite-firm — the SME's own settings
 *     screen — where the pending invite is listed alongside an
 *     Accept / Decline pair. We deliberately don't auto-link by token
 *     in the email: the recipient still has to authenticate as a
 *     tenant admin before accepting, which stops anyone who
 *     intercepts the email from grabbing the connection.
 */
class FirmInviteExistingClient extends Mailable
{
    use Queueable, SerializesModels;

    public Firm $firm;
    public FirmInvitation $invitation;
    public ?string $inviterName;
    public string $acceptUrl;

    public function __construct(Firm $firm, FirmInvitation $invitation, ?string $inviterName = null)
    {
        $this->firm        = $firm;
        $this->invitation  = $invitation;
        $this->inviterName = $inviterName;
        // Land on the SME's settings page; it lists incoming invites
        // and provides Accept / Decline buttons. /firm-invite/{token}
        // is reserved for the *opposite* direction (client → firm).
        $this->acceptUrl   = url('/settings/invite-firm');
    }

    public function build()
    {
        $appName = config('app.name');

        return $this
            ->from(config('mail.from.address'), $appName)
            ->subject($this->firm->name." would like to manage your books on {$appName}")
            ->view('emails.firm-invite-existing-client', [
                'firm'         => $this->firm,
                'inviterName'  => $this->inviterName,
                'acceptUrl'    => $this->acceptUrl,
                'expiresAt'    => $this->invitation->expires_at,
                'appName'      => $appName,
            ]);
    }
}
