<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TeamMemberWelcome extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Tenant $tenant,
        public string $role,
        public string $resetUrl,
        public ?string $inviterName = null,
    ) {}

    public function build()
    {
        $appName = config('app.name');
        $companyName = $this->tenant->display_name
            ?: ($this->tenant->legal_name ?: $this->tenant->id);

        return $this
            ->from(config('mail.from.address'), $appName)
            ->subject("You've been added to {$companyName} on {$appName}")
            ->view('emails.team-member-welcome', [
                'appName' => $appName,
                'companyName' => $companyName,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'role' => $this->role,
                'resetUrl' => $this->resetUrl,
                'inviterName' => $this->inviterName,
            ]);
    }
}
