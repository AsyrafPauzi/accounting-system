<?php

namespace App\Mail;

use App\Models\SubscriptionRenewal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubscriptionRenewalDue extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SubscriptionRenewal $renewal) {}

    public function build()
    {
        $planName = $this->renewal->plan?->name ?? 'your plan';
        $amount = number_format((float) $this->renewal->amount, 2);

        return $this->subject("BukuCloud renewal due — {$planName} (RM {$amount})")
            ->view('emails.subscription-renewal-due', [
                'renewal' => $this->renewal,
                'planName' => $planName,
                'amount' => $amount,
                'interval' => $this->renewal->interval,
                'dueAt' => $this->renewal->due_at?->toDateString(),
                'paymentUrl' => $this->renewal->payment_url,
            ]);
    }
}
