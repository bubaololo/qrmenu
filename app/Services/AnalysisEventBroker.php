<?php

namespace App\Services;

use App\Events\RealtimeEvent;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Log;

/**
 * Single chokepoint for realtime progress / order / notification events.
 *
 * Broadcasts each event over the configured driver (Reverb / WebSockets) as a
 * {@see RealtimeEvent}. The `$topic` is the Echo channel name and `$event` the
 * broadcast name (clients listen with a leading dot, e.g. `.analysis.started`).
 * Replaces the former Redis-list + SSE-poll mechanism; payloads and event names
 * are unchanged so existing consumers keep working.
 */
class AnalysisEventBroker
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(string $topic, string $event, array $payload): void
    {
        // Swallow ONLY transport-layer failures (Reverb daemon unreachable / HTTP
        // error) so a WebSocket outage can't break the business transaction
        // (order placement, image processing, analysis) that emitted the event —
        // but log them loudly so the outage is visible. Any other exception (a real
        // bug) is deliberately NOT caught and propagates so it surfaces immediately.
        try {
            broadcast(new RealtimeEvent($topic, $event, $payload));
        } catch (BroadcastException|ConnectException $e) {
            Log::error('Realtime broadcast transport failure', [
                'topic' => $topic,
                'event' => $event,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
