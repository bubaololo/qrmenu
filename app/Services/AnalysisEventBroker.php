<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

/**
 * Publishes per-analysis / per-translation progress events to Redis lists.
 *
 * Each topic maps to a Redis list `events:{topic}` capped by TTL. SSE clients
 * poll the list with a Last-Event-ID cursor to stream new events without
 * needing pub/sub (which is awkward inside PHP-FPM).
 */
class AnalysisEventBroker
{
    public const TTL_SECONDS = 3600;

    public const MAX_EVENTS = 500;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(string $topic, string $event, array $payload): void
    {
        $message = json_encode([
            'event' => $event,
            'data' => $payload,
            'ts' => microtime(true),
        ], JSON_UNESCAPED_UNICODE);

        $key = $this->key($topic);
        Redis::rpush($key, $message);
        Redis::ltrim($key, -self::MAX_EVENTS, -1);
        Redis::expire($key, self::TTL_SECONDS);
    }

    /**
     * Read all events on the topic from $sinceIndex (zero-based) to the latest.
     *
     * @return list<string> Raw JSON event strings in order.
     */
    public function readSince(string $topic, int $sinceIndex): array
    {
        $events = Redis::lrange($this->key($topic), $sinceIndex, -1);

        return is_array($events) ? array_values($events) : [];
    }

    public function totalEvents(string $topic): int
    {
        return (int) Redis::llen($this->key($topic));
    }

    private function key(string $topic): string
    {
        return 'events:'.$topic;
    }
}
