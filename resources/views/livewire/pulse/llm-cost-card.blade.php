<x-pulse::card :cols="$cols" :rows="$rows" :class="$class" wire:poll.5s="">
    <x-pulse::card-header name="LLM Cost">
        <x-slot:icon>
            <x-pulse::icons.rocket-launch />
        </x-slot:icon>
        <x-slot:actions>
            <span class="text-sm font-bold text-gray-900 dark:text-gray-100">
                ${{ number_format($totalCostCents / 100, 4) }}
            </span>
            <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">
                / {{ number_format($totalTokens) }} tok
            </span>
        </x-slot:actions>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand">
        @if ($models->isEmpty())
            <x-pulse::no-results />
        @else
            <x-pulse::table>
                <colgroup>
                    <col width="100%" />
                    <col width="0%" />
                    <col width="0%" />
                </colgroup>
                <x-pulse::thead>
                    <tr>
                        <x-pulse::th>Model</x-pulse::th>
                        <x-pulse::th class="text-right">Tokens</x-pulse::th>
                        <x-pulse::th class="text-right">Cost</x-pulse::th>
                    </tr>
                </x-pulse::thead>
                <tbody>
                    @foreach ($models as $model)
                        <tr class="h-2 first:h-0"></tr>
                        <tr>
                            <x-pulse::td>
                                <code class="text-xs text-gray-900 dark:text-gray-100">{{ $model->key }}</code>
                            </x-pulse::td>
                            <x-pulse::td numeric class="text-gray-500">
                                {{ number_format($model->tokens) }}
                            </x-pulse::td>
                            <x-pulse::td numeric class="font-medium text-gray-700 dark:text-gray-300">
                                @if ($model->cost_cents >= 1)
                                    ${{ number_format($model->cost_cents / 100, 2) }}
                                @else
                                    {{ number_format($model->cost_cents, 2) }}&#162;
                                @endif
                            </x-pulse::td>
                        </tr>
                    @endforeach
                </tbody>
            </x-pulse::table>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
