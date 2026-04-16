<x-pulse full-width>
    {{-- LLM Monitoring --}}
    <livewire:pulse.llm-health-card cols="8" />
    <livewire:pulse.llm-cost-card cols="4" />
    <livewire:pulse.llm-errors-card cols="12" expand />

    {{-- Infrastructure --}}
    <livewire:pulse.slow-outgoing-requests cols="6" />
    <livewire:pulse.slow-jobs cols="6" />
    <livewire:pulse.queues cols="6" />
    <livewire:pulse.exceptions cols="6" />

    {{-- Logs --}}
    <livewire:pulse.recent-logs-card cols="12" rows="4" expand />

    {{-- General --}}
    <livewire:pulse.usage cols="4" rows="2" />
    <livewire:pulse.slow-queries cols="8" />
    <livewire:pulse.cache cols="4" />
    <livewire:pulse.slow-requests cols="4" />
    <livewire:pulse.servers cols="4" />
</x-pulse>
