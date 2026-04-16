<x-pulse::card :cols="$cols" :rows="$rows" :class="$class" wire:poll.5s="">
    <x-pulse::card-header name="LLM Health">
        <x-slot:icon>
            <x-pulse::icons.circle-stack />
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand">
        @if ($models->isEmpty())
            <x-pulse::no-results />
        @else
            <x-pulse::table>
                <colgroup>
                    <col width="0%" />
                    <col width="100%" />
                    <col width="0%" />
                    <col width="0%" />
                    <col width="0%" />
                    <col width="0%" />
                </colgroup>
                <x-pulse::thead>
                    <tr>
                        <x-pulse::th>Status</x-pulse::th>
                        <x-pulse::th>Model</x-pulse::th>
                        <x-pulse::th class="text-right">Requests</x-pulse::th>
                        <x-pulse::th class="text-right">Errors</x-pulse::th>
                        <x-pulse::th class="text-right">Fallbacks</x-pulse::th>
                        <x-pulse::th class="text-right">Avg (ms)</x-pulse::th>
                    </tr>
                </x-pulse::thead>
                <tbody>
                    @foreach ($models as $model)
                        <tr class="h-2 first:h-0"></tr>
                        <tr>
                            <x-pulse::td>
                                @if ($model->success_rate >= 95)
                                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-green-500" title="{{ $model->success_rate }}%"></span>
                                @elseif ($model->success_rate >= 80)
                                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-yellow-500" title="{{ $model->success_rate }}%"></span>
                                @else
                                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-red-500" title="{{ $model->success_rate }}%"></span>
                                @endif
                            </x-pulse::td>
                            <x-pulse::td class="max-w-[1px]">
                                <code class="block text-xs text-gray-900 dark:text-gray-100 truncate" title="{{ $model->key }}">
                                    {{ $model->key }}
                                </code>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $model->success_rate }}% success
                                </p>
                            </x-pulse::td>
                            <x-pulse::td numeric class="text-gray-700 dark:text-gray-300 font-medium">
                                {{ number_format($model->total) }}
                            </x-pulse::td>
                            <x-pulse::td numeric class="{{ $model->errors > 0 ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-500' }}">
                                {{ $model->errors }}
                            </x-pulse::td>
                            <x-pulse::td numeric class="{{ $model->fallbacks > 0 ? 'text-yellow-600 dark:text-yellow-400 font-medium' : 'text-gray-500' }}">
                                {{ $model->fallbacks }}
                            </x-pulse::td>
                            <x-pulse::td numeric class="text-gray-700 dark:text-gray-300">
                                {{ number_format($model->avg_duration_ms) }}
                            </x-pulse::td>
                        </tr>
                    @endforeach
                </tbody>
            </x-pulse::table>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
