<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One-time code emailed to a user to confirm a sensitive action. Queued so a
 * mail-relay hiccup can't stall the request that triggered it.
 */
class ConfirmationCode extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $code) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your confirmation code')
            ->line('Use this code to confirm the action you just started:')
            ->line("**{$this->code}**")
            ->line('The code is valid for 10 minutes. If you did not request it, you can ignore this email.');
    }
}
