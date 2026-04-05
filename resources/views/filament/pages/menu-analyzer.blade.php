<x-filament::page>
    {{ $this->form }}

    @foreach ($this->results as $result)
        <div class="mt-6">
            <div class="flex items-center gap-2 mb-3 flex-wrap">
                <x-filament::icon icon="heroicon-o-photo" class="h-5 w-5 text-gray-400 shrink-0" />
                @foreach ($result['paths'] as $path)
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">
                        {{ basename($path) }}
                    </span>
                @endforeach
                @if ($result['error'])
                    <x-filament::badge color="danger">Error</x-filament::badge>
                @else
                    <x-filament::badge color="success">{{ count($result['items']) }} items</x-filament::badge>
                @endif
            </div>

            @if ($result['error'])
                <x-filament::section>
                    <pre class="text-sm text-danger-600 overflow-x-auto whitespace-pre-wrap">{{ $result['error'] }}</pre>
                </x-filament::section>
            @else
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 mb-3">
                    @foreach ($result['items'] as $item)
                        <x-filament::section>
                            @if (!empty($item['category']))
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">
                                    {{ $item['category'] }}
                                </p>
                            @endif
                            <p class="font-bold text-gray-900 dark:text-white">
                                {{ $item['name_en'] ?? $item['original_name'] ?? '—' }}
                            </p>
                            @if (!empty($item['original_name']) && isset($item['name_en']))
                                <p class="text-sm text-gray-500">{{ $item['original_name'] }}</p>
                            @endif
                            @if (!empty($item['description_en']))
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2 leading-relaxed">
                                    {{ $item['description_en'] }}
                                </p>
                            @endif
                            @if (!empty($item['price']))
                                <div class="mt-2">
                                    <x-filament::badge color="primary">
                                        {{ $item['price'] }} {{ $item['currency'] ?? '' }}
                                    </x-filament::badge>
                                </div>
                            @endif
                        </x-filament::section>
                    @endforeach
                </div>

                <details>
                    <summary class="cursor-pointer text-xs text-gray-400 hover:text-gray-600 select-none">
                        Raw JSON
                    </summary>
                    <x-filament::section class="mt-2">
                        <pre class="text-xs overflow-x-auto leading-relaxed text-gray-700 dark:text-gray-300 whitespace-pre">{{ json_encode($result['items'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </x-filament::section>
                </details>
            @endif
        </div>

        @unless ($loop->last)
            <hr class="my-6 border-gray-200 dark:border-gray-700">
        @endunless
    @endforeach
</x-filament::page>
