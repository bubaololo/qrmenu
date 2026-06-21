<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued variant of the email-verification notification. Sending happens on the
 * Horizon worker, so a slow or rejecting mail relay can't fail (or stall) the
 * registration request. The SPA verification URL is still applied via
 * VerifyEmail::createUrlUsing in AppServiceProvider.
 */
class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;
}
