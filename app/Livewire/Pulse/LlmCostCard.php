<?php

namespace App\Livewire\Pulse;

use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

#[Lazy]
class LlmCostCard extends Card
{
    public function render()
    {
        $costs = $this->aggregate('llm_cost', ['sum']);
        $tokens = $this->aggregate('llm_tokens', ['sum'])->keyBy('key');
        $totalCostCents = $this->aggregateTotal('llm_cost', 'sum') ?? 0;
        $totalTokens = $this->aggregateTotal('llm_tokens', 'sum') ?? 0;

        $models = $costs->map(fn ($row) => (object) [
            'key' => $row->key,
            'cost_cents' => round($row->sum, 4),
            'tokens' => (int) ($tokens[$row->key]?->sum ?? 0),
        ])->sortByDesc('cost_cents');

        return view('livewire.pulse.llm-cost-card', [
            'models' => $models,
            'totalCostCents' => round($totalCostCents, 4),
            'totalTokens' => (int) $totalTokens,
        ]);
    }
}
