<?php

namespace App\Livewire\Pulse;

use App\Enums\LlmRequestStatus;
use App\Models\LlmRequest;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

#[Lazy]
class LlmErrorsCard extends Card
{
    public function render()
    {
        $period = $this->periodAsInterval();

        $since = now()->sub($period);

        $recentErrors = LlmRequest::query()
            ->where('status', '!=', LlmRequestStatus::Success)
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['model', 'status', 'error_class', 'error_message', 'duration_ms', 'created_at']);

        $fallbackCount = LlmRequest::query()
            ->where('tier_position', '>', 0)
            ->where('created_at', '>=', $since)
            ->count();

        $totalCount = LlmRequest::query()
            ->where('created_at', '>=', $since)
            ->count();

        return view('livewire.pulse.llm-errors-card', [
            'recentErrors' => $recentErrors,
            'fallbackCount' => $fallbackCount,
            'totalCount' => $totalCount,
            'fallbackRate' => $totalCount > 0 ? round($fallbackCount / $totalCount * 100, 1) : 0,
        ]);
    }
}
