<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmail extends BaseVerifyEmail
{
    /**
     * Build BukuCloud's verification email while keeping Laravel's signed URL.
     */
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your BukuCloud email')
            ->greeting('One small thing: verify your email')
            ->line('Click the button below to confirm your email address and keep your account notifications working.')
            ->action('Verify email address', $url)
            ->line('If you did not create a BukuCloud account, you can safely ignore this email.');
    }
}
