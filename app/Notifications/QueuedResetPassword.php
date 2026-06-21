<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued variant of the password-reset notification — keeps the forgot-password
 * endpoint fast and resilient to mail-relay errors. The SPA reset URL is applied
 * via ResetPassword::createUrlUsing in AppServiceProvider.
 */
class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
