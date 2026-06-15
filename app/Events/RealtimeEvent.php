<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Generic realtime broadcast envelope.
 *
 * One reusable event so every progress/order/notification stream can be emitted
 * through a single chokepoint (AnalysisEventBroker::publish). The topic doubles
 * as the Echo channel name; `restaurant*` topics are private (membership-gated in
 * routes/channels.php), everything else is public (the unguessable uuid/id in the
 * topic is the bearer token).
 */
class RealtimeEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $topic,
        public string $event,
        public array $payload,
    ) {}

    public function broadcastOn(): Channel
    {
        return (str_starts_with($this->topic, 'restaurant-orders.') || str_starts_with($this->topic, 'restaurant.'))
            ? new PrivateChannel($this->topic)
            : new Channel($this->topic);
    }

    public function broadcastAs(): string
    {
        return $this->event;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
