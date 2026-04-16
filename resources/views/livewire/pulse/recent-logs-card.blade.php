<x-pulse::card :cols="$cols" :rows="$rows" :class="$class" wire:poll.5s="">
    <x-pulse::card-header name="Recent Logs">
        <x-slot:icon>
            <x-pulse::icons.command-line />
        </x-slot:icon>
        <x-slot:actions>
            <div class="flex gap-1">
                @foreach ($channels as $ch)
                    <button
                        wire:click="$set('channel', '{{ $ch }}')"
                        class="px-2 py-0.5 text-xs rounded {{ $channel === $ch ? 'bg-primary-500 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700' }}"
                    >
                        {{ ucfirst($ch) }}
                    </button>
                @endforeach
            </div>
        </x-slot:actions>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand">
        @if ($entries->isEmpty())
            <x-pulse::no-results />
        @else
            <div class="space-y-0.5">
                @foreach ($entries as $entry)
                    <div class="flex gap-2 items-start px-3 py-1 text-xs font-mono hover:bg-gray-50 dark:hover:bg-gray-900/50">
                        <span class="shrink-0 text-gray-400 dark:text-gray-500 w-14">
                            {{ \Illuminate\Support\Str::substr($entry->time, 11, 8) }}
                        </span>
                        <span class="shrink-0 w-10">
                            @switch($entry->level)
                                @case('error')
                                @case('critical')
                                @case('emergency')
                                    <span class="text-red-600 dark:text-red-400 font-bold uppercase">{{ $entry->level }}</span>
                                    @break
                                @case('warning')
                                    <span class="text-yellow-600 dark:text-yellow-400 font-bold uppercase">warn</span>
                                    @break
                                @default
                                    <span class="text-gray-400 dark:text-gray-500 uppercase">{{ Str::limit($entry->level, 5, '') }}</span>
                            @endswitch
                        </span>
                        <span class="shrink-0 px-1 rounded {{ $entry->channel === 'llm' ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400' : 'bg-gray-100 dark:bg-gray-800 text-gray-500' }}">
                            {{ $entry->channel }}
                        </span>
                        <span class="text-gray-700 dark:text-gray-300 truncate" title="{{ $entry->message }}">
                            {{ $entry->message }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
