<?php

namespace App\Livewire\Pulse;

use Illuminate\Support\Collection;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;

#[Lazy]
class RecentLogsCard extends Card
{
    #[Url]
    public string $channel = 'all';

    private const MAX_ENTRIES = 50;

    public function render()
    {
        $entries = $this->readLogs();

        return view('livewire.pulse.recent-logs-card', [
            'entries' => $entries,
            'channels' => ['all', 'app', 'llm'],
        ]);
    }

    /**
     * @return Collection<int, object{level: string, message: string, channel: string, time: string}>
     */
    private function readLogs(): Collection
    {
        $entries = collect();

        if ($this->channel === 'all' || $this->channel === 'app') {
            $entries = $entries->merge($this->parseLogFile(storage_path('logs/laravel.log'), 'app'));
        }

        if ($this->channel === 'all' || $this->channel === 'llm') {
            $entries = $entries->merge($this->parseLogFile(storage_path('logs/llm.log'), 'llm'));

            $today = now()->format('Y-m-d');
            $entries = $entries->merge($this->parseLogFile(storage_path("logs/llm-{$today}.log"), 'llm'));
        }

        return $entries
            ->sortByDesc('time')
            ->take(self::MAX_ENTRIES)
            ->values();
    }

    /**
     * @return Collection<int, object>
     */
    private function parseLogFile(string $path, string $channel): Collection
    {
        if (! file_exists($path)) {
            return collect();
        }

        $lines = array_slice(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -100);

        return collect($lines)
            ->filter(fn (string $line) => preg_match('/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2})/', $line))
            ->map(function (string $line) use ($channel) {
                preg_match('/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2})[^\]]*\]\s+\S+\.(\w+):\s+(.*)$/', $line, $m);

                if (empty($m)) {
                    return null;
                }

                return (object) [
                    'time' => $m[1],
                    'level' => strtolower($m[2]),
                    'message' => mb_substr($m[3], 0, 200),
                    'channel' => $channel,
                ];
            })
            ->filter();
    }
}
