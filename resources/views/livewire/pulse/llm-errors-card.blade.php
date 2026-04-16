<x-pulse::card :cols="$cols" :rows="$rows" :class="$class" wire:poll.5s="">
    <x-pulse::card-header name="LLM Errors & Fallbacks">
        <x-slot:icon>
            <x-pulse::icons.bug-ant />
        </x-slot:icon>
        <x-slot:actions>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                {{ $fallbackCount }} fallback{{ $fallbackCount !== 1 ? 's' : '' }} ({{ $fallbackRate }}%)
                / {{ $totalCount }} total
            </span>
        </x-slot:actions>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand">
        @if ($recentErrors->isEmpty())
            <div class="flex items-center justify-center h-full text-sm text-gray-500 dark:text-gray-400">
                No errors in this period
            </div>
        @else
            <x-pulse::table>
                <colgroup>
                    <col width="0%" />
                    <col width="0%" />
                    <col width="100%" />
                    <col width="0%" />
                </colgroup>
                <x-pulse::thead>
                    <tr>
                        <x-pulse::th>Time</x-pulse::th>
                        <x-pulse::th>Model</x-pulse::th>
                        <x-pulse::th>Error</x-pulse::th>
                        <x-pulse::th class="text-right">Duration</x-pulse::th>
                    </tr>
                </x-pulse::thead>
                <tbody>
                    @foreach ($recentErrors as $error)
                        <tr class="h-2 first:h-0"></tr>
                        <tr>
                            <x-pulse::td class="whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                {{ $error->created_at->diffForHumans(short: true) }}
                            </x-pulse::td>
                            <x-pulse::td class="whitespace-nowrap">
                                <code class="text-xs">{{ $error->model }}</code>
                            </x-pulse::td>
                            <x-pulse::td class="max-w-[1px]">
                                <p class="text-xs truncate text-red-600 dark:text-red-400" title="{{ $error->error_message }}">
                                    {{ Str::limit($error->error_message, 120) }}
                                </p>
                                @if ($error->error_class)
                                    <p class="text-xs text-gray-400 truncate">{{ class_basename($error->error_class) }}</p>
                                @endif
                            </x-pulse::td>
                            <x-pulse::td numeric class="text-gray-500">
                                {{ number_format($error->duration_ms) }}ms
                            </x-pulse::td>
                        </tr>
                    @endforeach
                </tbody>
            </x-pulse::table>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
