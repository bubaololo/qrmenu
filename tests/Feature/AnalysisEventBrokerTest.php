<?php

namespace Tests\Feature;

use App\Events\RealtimeEvent;
use App\Services\AnalysisEventBroker;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnalysisEventBrokerTest extends TestCase
{
    #[Test]
    public function it_broadcasts_a_realtime_event_with_topic_event_and_payload(): void
    {
        Event::fake([RealtimeEvent::class]);

        app(AnalysisEventBroker::class)->publish('menu-analysis.abc', 'analysis.started', ['chunk_total' => 2]);

        Event::assertDispatched(RealtimeEvent::class, function (RealtimeEvent $e): bool {
            return $e->topic === 'menu-analysis.abc'
                && $e->event === 'analysis.started'
                && $e->payload === ['chunk_total' => 2]
                && $e->broadcastAs() === 'analysis.started'
                && $e->broadcastWith() === ['chunk_total' => 2];
        });
    }

    #[Test]
    public function public_topics_broadcast_on_a_public_channel(): void
    {
        Event::fake([RealtimeEvent::class]);

        app(AnalysisEventBroker::class)->publish('menu-translation.5.fr', 'translation.started', []);

        Event::assertDispatched(RealtimeEvent::class, function (RealtimeEvent $e): bool {
            $channel = $e->broadcastOn();

            return $channel instanceof Channel
                && ! $channel instanceof PrivateChannel
                && $channel->name === 'menu-translation.5.fr';
        });
    }

    #[Test]
    public function restaurant_topics_broadcast_on_a_private_channel(): void
    {
        Event::fake([RealtimeEvent::class]);

        app(AnalysisEventBroker::class)->publish('restaurant-orders.7', 'order.placed', []);
        app(AnalysisEventBroker::class)->publish('restaurant.7', 'menu-item.updated', []);

        Event::assertDispatched(
            RealtimeEvent::class,
            fn (RealtimeEvent $e): bool => $e->topic === 'restaurant-orders.7' && $e->broadcastOn() instanceof PrivateChannel,
        );
        Event::assertDispatched(
            RealtimeEvent::class,
            fn (RealtimeEvent $e): bool => $e->topic === 'restaurant.7' && $e->broadcastOn() instanceof PrivateChannel,
        );
    }

    #[Test]
    public function it_swallows_a_transport_failure_and_logs(): void
    {
        Log::spy();
        $this->bindBroadcasterThrowing(new BroadcastException('reverb unreachable'));

        // Must NOT throw — a Reverb outage cannot break the emitting transaction.
        app(AnalysisEventBroker::class)->publish('menu-analysis.x', 'analysis.started', []);

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message): bool => $message === 'Realtime broadcast transport failure')
            ->once();
    }

    #[Test]
    public function it_rethrows_a_non_transport_error(): void
    {
        $this->bindBroadcasterThrowing(new \RuntimeException('a real bug'));

        // A non-transport error (e.g. a programming bug) must surface, not hide.
        $this->expectException(\RuntimeException::class);
        app(AnalysisEventBroker::class)->publish('menu-analysis.x', 'analysis.started', []);
    }

    private function bindBroadcasterThrowing(\Throwable $e): void
    {
        Broadcast::extend('throwing', fn (): Broadcaster => new class($e) implements Broadcaster
        {
            public function __construct(private \Throwable $e) {}

            public function auth($request) {}

            public function validAuthenticationResponse($request, $result) {}

            public function broadcast(array $channels, $event, array $payload = [])
            {
                throw $this->e;
            }
        });

        config([
            'broadcasting.default' => 'throwing',
            'broadcasting.connections.throwing' => ['driver' => 'throwing'],
        ]);
    }
}
