<?php

namespace App\Notifications;

use App\Services\AnalysisEventBroker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Ad-hoc web-push notification used by the /test-menu push harness. Queued and
 * best-effort: a failed delivery must never break the caller, mirroring how
 * {@see AnalysisEventBroker} treats broadcast failures.
 *
 * Once orders ship, the order-event notification can reuse this same shape
 * (build a WebPushMessage, deliver via WebPushChannel).
 */
class TestPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
    ) {}

    /**
     * @return array<int, class-string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->icon('/pwa-192x192.svg')
            ->body($this->body)
            ->data(['url' => '/'])
            ->options(['TTL' => 600]);
    }
}
