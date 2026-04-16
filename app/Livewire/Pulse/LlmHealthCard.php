<?php

namespace App\Livewire\Pulse;

use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

#[Lazy]
class LlmHealthCard extends Card
{
    public function render()
    {
        $requests = $this->aggregate('llm_request', ['avg', 'count']);
        $errors = $this->aggregate('llm_error', ['count'])->keyBy('key');
        $fallbacks = $this->aggregate('llm_fallback', ['count'])->keyBy('key');

        $models = $requests->map(function ($row) use ($errors, $fallbacks) {
            $totalCount = (int) $row->count;
            $errorCount = (int) ($errors[$row->key]?->count ?? 0);
            $fallbackCount = (int) ($fallbacks[$row->key]?->count ?? 0);
            $successRate = $totalCount > 0 ? round(($totalCount - $errorCount) / $totalCount * 100, 1) : 0;

            return (object) [
                'key' => $row->key,
                'total' => $totalCount,
                'errors' => $errorCount,
                'fallbacks' => $fallbackCount,
                'avg_duration_ms' => round($row->avg),
                'success_rate' => $successRate,
            ];
        })->sortByDesc('total');

        return view('livewire.pulse.llm-health-card', [
            'models' => $models,
        ]);
    }
}
