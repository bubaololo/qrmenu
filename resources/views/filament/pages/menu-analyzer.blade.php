<x-filament::page>
    {{ $this->form }}

    @if ($this->pendingAnalysisId)
        <div wire:poll.5s="checkAnalysisStatus" class="mt-6 flex items-center gap-3 p-4 rounded-lg bg-primary-50 dark:bg-primary-900/20">
            <x-filament::loading-indicator class="h-5 w-5 text-primary-500" />
            <span class="text-sm font-medium text-primary-700 dark:text-primary-300">
                Processing menu images...
            </span>
        </div>
    @endif

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
                    @php
                        $menuForCount = $result['menu'] ?? [];
                    @endphp
                    <x-filament::badge color="success">{{ \App\Support\MenuJson::dishCount($menuForCount) }} items</x-filament::badge>
                @endif
            </div>

            @if ($result['error'])
                <x-filament::section>
                    <pre class="text-sm text-danger-600 overflow-x-auto whitespace-pre-wrap">{{ $result['error'] }}</pre>
                </x-filament::section>
            @else
                @php
                    $menu = $result['menu'] ?? [];
                    $defaultCurrency = data_get($menu, 'restaurant.currency');
                @endphp

                @if (! empty($menu['restaurant']) && is_array($menu['restaurant']))
                    @php
                        $r = $menu['restaurant'];
                        $rn = \App\Support\MenuJson::bilingualPair($r['name'] ?? null);
                        $ra = \App\Support\MenuJson::bilingualPair($r['address'] ?? null);
                    @endphp
                    <x-filament::section class="mb-4">
                        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-2">
                            Restaurant
                        </p>
                        <p class="font-bold text-lg text-gray-900 dark:text-white">
                            {{ $rn['primary'] ?: '—' }}
                        </p>
                        @if ($rn['secondary'])
                            <p class="text-sm text-gray-500">{{ $rn['secondary'] }}</p>
                        @endif
                        @if ($ra['primary'])
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">{{ $ra['primary'] }}</p>
                        @endif
                        @if ($ra['secondary'])
                            <p class="text-sm text-gray-500">{{ $ra['secondary'] }}</p>
                        @endif
                        <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500">
                            @if (! empty($r['city']))
                                <span>{{ $r['city'] }}</span>
                            @endif
                            @if (! empty($r['district']))
                                <span>{{ $r['district'] }}</span>
                            @endif
                            @if (! empty($r['opening_hours']))
                                <span>{{ is_array($r['opening_hours']) ? json_encode($r['opening_hours']) : $r['opening_hours'] }}</span>
                            @endif
                        </div>
                    </x-filament::section>
                @endif

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 mb-3">
                    @foreach (\App\Support\MenuJson::sections($menu) as $idx => $section)
                        @php
                            $cat = \App\Support\MenuJson::bilingualPair($section['category_name'] ?? null);
                        @endphp
                        <div class="col-span-full pt-2 pb-1 border-b border-gray-200 dark:border-gray-700">
                            <p class="text-sm font-bold text-gray-800 dark:text-gray-200">
                                {{ $cat['primary'] ?: 'Section '.($idx + 1) }}
                            </p>
                            @if ($cat['secondary'])
                                <p class="text-xs text-gray-500">{{ $cat['secondary'] }}</p>
                            @endif
                        </div>

                        @foreach ($section['items'] ?? [] as $item)
                            @php
                                $np = \App\Support\MenuJson::bilingualPair($item['name'] ?? null);
                                $dp = \App\Support\MenuJson::bilingualPair($item['description'] ?? null);
                                $priceLine = is_array($item['price'] ?? null)
                                    ? \App\Support\MenuJson::formatPriceDisplay($item['price'], $defaultCurrency)
                                    : '';
                            @endphp
                            <x-filament::section>
                                <p class="font-bold text-gray-900 dark:text-white">
                                    {{ $np['primary'] ?: '—' }}
                                </p>
                                @if ($np['secondary'])
                                    <p class="text-sm text-gray-500">{{ $np['secondary'] }}</p>
                                @endif
                                @if ($dp['primary'])
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2 leading-relaxed">
                                        {{ $dp['primary'] }}
                                    </p>
                                @endif
                                @if ($dp['secondary'])
                                    <p class="text-sm text-gray-500 mt-1">{{ $dp['secondary'] }}</p>
                                @endif
                                @if ($priceLine !== '')
                                    <div class="mt-2">
                                        <x-filament::badge color="primary">
                                            {{ $priceLine }}
                                        </x-filament::badge>
                                    </div>
                                @endif
                                @if (! empty($item['variations']) && is_array($item['variations']))
                                    <div class="mt-3 space-y-1 border-t border-gray-100 dark:border-gray-800 pt-3 text-xs text-gray-600 dark:text-gray-400">
                                        @foreach ($item['variations'] as $var)
                                            @php
                                                $vp = \App\Support\MenuJson::bilingualPair($var['name'] ?? null);
                                                $vPrice = is_array($var['price'] ?? null)
                                                    ? \App\Support\MenuJson::formatPriceDisplay($var['price'], $defaultCurrency)
                                                    : '';
                                                $vLabel = $vp['primary'];
                                                if ($vp['secondary']) {
                                                    $vLabel .= ' · '.$vp['secondary'];
                                                }
                                            @endphp
                                            <p>
                                                <span>{{ $vLabel }}</span>
                                                @if ($vPrice !== '')
                                                    <span class="font-semibold text-gray-800 dark:text-gray-200"> — {{ $vPrice }}</span>
                                                @endif
                                            </p>
                                        @endforeach
                                    </div>
                                @endif
                            </x-filament::section>
                        @endforeach
                    @endforeach
                </div>

                <details>
                    <summary class="cursor-pointer text-xs text-gray-400 hover:text-gray-600 select-none">
                        Raw JSON
                    </summary>
                    <x-filament::section class="mt-2">
                        <pre class="text-xs overflow-x-auto leading-relaxed text-gray-700 dark:text-gray-300 whitespace-pre">{{ json_encode($menu, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </x-filament::section>
                </details>
            @endif
        </div>

        @unless ($loop->last)
            <hr class="my-6 border-gray-200 dark:border-gray-700">
        @endunless
    @endforeach
</x-filament::page>
