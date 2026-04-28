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

        return $this->stream(
            topic: "menu-analysis.{$uuid}",
            sinceIndex: (int) $request->header('Last-Event-ID', 0),
            isTerminal: function () use ($uuid): bool {
                $a = MenuAnalysis::where('uuid', $uuid)->first();

                return $a !== null && in_array($a->status->value, ['completed', 'failed'], true);
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

    private function stream(string $topic, int $sinceIndex, callable $isTerminal): StreamedResponse
    {
        $broker = $this->broker;

        return new StreamedResponse(function () use ($topic, $sinceIndex, $isTerminal, $broker): void {
            // Discourage proxy/output buffering. Nginx respects X-Accel-Buffering: no.
            @ini_set('output_buffering', '0');
            @ini_set('zlib.output_compression', '0');

            $cursor = max(0, $sinceIndex);

            // Emit a hello so the client knows we connected.
            echo "retry: 3000\n\n";
            @flush();

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
