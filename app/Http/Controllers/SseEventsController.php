<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\MenuAnalysis;
use App\Services\AnalysisEventBroker;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SseEventsController extends Controller
{
    public function __construct(private readonly AnalysisEventBroker $broker) {}

    /**
     * Stream events for a menu analysis (`menu-analysis.{uuid}` topic).
     *
     * Per-pack progress: started → chunk-complete (×N) → completed/failed.
     */
    public function menuAnalysis(Request $request, string $uuid): StreamedResponse
    {
        $analysis = MenuAnalysis::where('uuid', $uuid)->firstOrFail();
        if ($analysis->user_id !== null && $analysis->user_id !== $request->user()?->id) {
            abort(403);
        }

        $topic = "menu-analysis.{$uuid}";

        return $this->stream(
            topic: $topic,
            sinceIndex: (int) $request->header('Last-Event-ID', 0),
            isTerminal: function () use ($uuid): bool {
                $a = MenuAnalysis::where('uuid', $uuid)->first();

                return $a !== null && in_array($a->status->value, ['completed', 'failed'], true);
            },
            // For analyses that are ALREADY terminal when the client connects but
            // have no events in the broker (events expired, or this analysis ran
            // on a code revision that didn't publish), synthesize a terminal event
            // from the DB row so the client doesn't reconnect-loop forever.
            synthesizeTerminal: function () use ($uuid): ?array {
                $a = MenuAnalysis::where('uuid', $uuid)->first();
                if ($a === null) {
                    return null;
                }

                return match ($a->status->value) {
                    'completed' => [
                        'event' => 'analysis.completed',
                        'data' => [
                            'menu_id' => $a->result_menu_id,
                            'restaurant_id' => $a->restaurant_id,
                            'item_count' => $a->result_item_count ?? 0,
                            'synthesized' => true,
                        ],
                        'ts' => microtime(true),
                    ],
                    'failed' => [
                        'event' => 'analysis.failed',
                        'data' => [
                            'error' => $a->error_message ?? 'Analysis failed.',
                            'synthesized' => true,
                        ],
                        'ts' => microtime(true),
                    ],
                    default => null,
                };
            },
        );
    }

    /**
     * Stream events for a translation run (`menu-translation.{menuId}.{locale}` topic).
     *
     * Intentionally public — anonymous QR-scanning users on the public menu
     * page need to know when translations land so the page can refresh.
     * Topic content is harmless (chunk counts, no menu data).
     */
    public function menuTranslation(Request $request, Menu $menu, string $locale): StreamedResponse
    {
        $topic = "menu-translation.{$menu->id}.{$locale}";

        return $this->stream(
            topic: $topic,
            sinceIndex: (int) $request->header('Last-Event-ID', 0),
            // Translation has no per-run terminal flag in DB; we close after the
            // event log signals 'translation.completed'.
            isTerminal: function () use ($topic): bool {
                $events = $this->broker->readSince($topic, 0);
                foreach ($events as $raw) {
                    $decoded = json_decode($raw, true);
                    if (($decoded['event'] ?? null) === 'translation.completed') {
                        return true;
                    }
                }

                return false;
            },
        );
    }

    /**
     * @param  callable(): ?array{event: string, data: array<string, mixed>, ts: float}  $synthesizeTerminal
     */
    private function stream(
        string $topic,
        int $sinceIndex,
        callable $isTerminal,
        ?callable $synthesizeTerminal = null,
    ): StreamedResponse {
        $broker = $this->broker;

        return new StreamedResponse(function () use ($topic, $sinceIndex, $isTerminal, $synthesizeTerminal, $broker): void {
            // Discourage proxy/output buffering. Nginx respects X-Accel-Buffering: no.
            @ini_set('output_buffering', '0');
            @ini_set('zlib.output_compression', '0');

            // PHP-FPM and Laravel may have started output buffering above us — close every
            // remaining ob layer so each echo + flush() goes straight to FPM → nginx → client.
            // Without this, Laravel's middleware ob_start() can hold the SSE stream until the
            // response finishes, which for SSE means "never".
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            @ob_implicit_flush(true);

            $cursor = max(0, $sinceIndex);

            // Emit a hello so the client knows we connected. Padding forces some
            // intermediaries to flush their internal 4KB/16KB buffer right away.
            echo "retry: 3000\n";
            echo ': '.str_repeat(' ', 2048)."\n\n";
            @flush();

            $emittedEventCount = 0;
            $idleTicks = 0;
            $maxIdleSeconds = 300;        // safety cap to avoid leaking workers
            $heartbeatEvery = 25;         // < typical fastcgi_read_timeout (30-60s)
            $sleepSeconds = 1;

            while (true) {
                if (connection_aborted()) {
                    return;
                }

                $events = $broker->readSince($topic, $cursor);
                foreach ($events as $raw) {
                    echo "id: {$cursor}\n";
                    echo 'data: '.$raw."\n\n";
                    $cursor++;
                    $emittedEventCount++;
                    @flush();
                    $idleTicks = 0;
                }

                if (empty($events)) {
                    $idleTicks++;
                    if ($idleTicks % $heartbeatEvery === 0) {
                        echo ": heartbeat\n\n";
                        @flush();
                    }
                }

                if ($isTerminal()) {
                    // Drain any final events emitted before terminal flag flipped.
                    foreach ($broker->readSince($topic, $cursor) as $raw) {
                        echo "id: {$cursor}\n";
                        echo 'data: '.$raw."\n\n";
                        $cursor++;
                        $emittedEventCount++;
                    }

                    // If we never emitted any events during this stream AND the broker
                    // had nothing for this topic AND the analysis is already terminal,
                    // synthesize a terminal event from the DB so the client doesn't
                    // reconnect-loop. Happens for old analyses whose events expired,
                    // or analyses that ran on code revisions without SSE publishing.
                    if ($emittedEventCount === 0 && $broker->totalEvents($topic) === 0 && $synthesizeTerminal !== null) {
                        $synthetic = $synthesizeTerminal();
                        if ($synthetic !== null) {
                            echo "id: {$cursor}\n";
                            echo 'data: '.json_encode($synthetic, JSON_UNESCAPED_UNICODE)."\n\n";
                        }
                    }

                    @flush();

                    return;
                }

                if ($idleTicks * $sleepSeconds >= $maxIdleSeconds) {
                    return;
                }

                sleep($sleepSeconds);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, private',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
