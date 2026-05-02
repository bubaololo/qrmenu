<x-filament::page>
    {{ $this->form }}

    @if ($this->pendingAnalysisId)
        <div
            wire:key="analysis-progress-{{ $this->pendingAnalysisUuid }}"
            x-data="analysisProgress(@js($this->pendingAnalysisUuid))"
            class="mt-6 p-4 rounded-lg bg-primary-50 dark:bg-primary-900/20"
        >
            <ul class="space-y-2.5">
                <template x-for="stage in stages" :key="stage.key">
                    <li class="flex items-start gap-3 text-sm">
                        <span class="mt-0.5 shrink-0 w-5 h-5 flex items-center justify-center">
                            <template x-if="stage.status === 'pending'">
                                <span class="block w-2 h-2 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                            </template>
                            <template x-if="stage.status === 'in_progress'">
                                <svg class="animate-spin w-4 h-4 text-primary-500" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                            </template>
                            <template x-if="stage.status === 'done'">
                                <svg class="w-4 h-4 text-success-500" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </template>
                            <template x-if="stage.status === 'failed'">
                                <svg class="w-4 h-4 text-danger-500" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </template>
                            <template x-if="stage.status === 'skipped'">
                                <span class="block w-2 h-0.5 bg-gray-400 dark:bg-gray-500"></span>
                            </template>
                        </span>
                        <div class="flex-1 min-w-0">
                            <p
                                class="font-medium"
                                :class="{
                                    'text-gray-400 dark:text-gray-500': stage.status === 'pending' || stage.status === 'skipped',
                                    'text-primary-700 dark:text-primary-300': stage.status === 'in_progress',
                                    'text-gray-700 dark:text-gray-300': stage.status === 'done',
                                    'text-danger-600 dark:text-danger-400': stage.status === 'failed',
                                }"
                                x-text="stage.label"
                            ></p>
                            <p
                                x-show="stage.detail"
                                class="text-xs mt-0.5 text-gray-500 dark:text-gray-400"
                                x-text="stage.detail"
                            ></p>
                        </div>
                    </li>
                </template>
            </ul>
            <p
                x-show="error"
                class="mt-3 text-sm text-danger-600 dark:text-danger-400"
                x-text="error"
            ></p>

            <details class="mt-4" x-show="logEntries.length > 0">
                <summary class="cursor-pointer text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 select-none">
                    Live log <span class="text-gray-400" x-text="`(${logEntries.length})`"></span>
                </summary>
                <div
                    x-ref="logBox"
                    class="mt-2 max-h-64 overflow-y-auto rounded border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 p-2 font-mono text-[11px] leading-relaxed"
                >
                    <template x-for="(entry, idx) in logEntries" :key="idx">
                        <div class="flex items-start gap-2">
                            <span class="shrink-0 text-gray-400 dark:text-gray-500" x-text="entry.ts"></span>
                            <span
                                class="shrink-0 uppercase font-semibold w-16"
                                :class="{
                                    'text-gray-500': entry.level === 'info',
                                    'text-warning-600 dark:text-warning-400': entry.level === 'warn',
                                    'text-danger-600 dark:text-danger-400': entry.level === 'error',
                                    'text-success-600 dark:text-success-400': entry.level === 'ok',
                                }"
                                x-text="entry.level"
                            ></span>
                            <span class="text-gray-700 dark:text-gray-300 break-all" x-text="entry.message"></span>
                        </div>
                    </template>
                </div>
            </details>
        </div>

        <script>
            window.analysisProgress = function (uuid) {
                return {
                    uuid,
                    error: '',
                    es: null,
                    chunkTotal: 0,
                    logEntries: [],
                    stages: [
                        { key: 'queued',   label: 'Queued for processing',   status: 'pending', detail: '' },
                        { key: 'prep',     label: 'Preparing images (rotate · crop · deskew)', status: 'pending', detail: '' },
                        { key: 'vision',   label: 'Analyzing menu with vision LLM', status: 'pending', detail: '' },
                        { key: 'save',     label: 'Saving menu data',         status: 'pending', detail: '' },
                        { key: 'crops',    label: 'Cropping dish photos',     status: 'pending', detail: '' },
                        { key: 'done',     label: 'Done',                     status: 'pending', detail: '' },
                    ],
                    preflightDone: 0,
                    preflightTotal: 0,
                    preprocessDone: 0,
                    preprocessTotal: 0,
                    setStage(key, status, detail) {
                        const s = this.stages.find(s => s.key === key);
                        if (!s) return;
                        s.status = status;
                        if (detail !== undefined) s.detail = detail;
                    },
                    advance(key) {
                        // Set this stage to in_progress; mark all earlier stages as done.
                        // Earlier 'pending' becomes 'done' too — we logically passed them
                        // even if we missed their explicit event (e.g. SSE replay or chunked path).
                        for (const s of this.stages) {
                            if (s.key === key) { s.status = 'in_progress'; return; }
                            if (s.status === 'pending' || s.status === 'in_progress') s.status = 'done';
                        }
                    },
                    appendLog(level, message, ts) {
                        // ts arrives as a unix timestamp (seconds, float) from the broker;
                        // fall back to "now" if missing.
                        const date = ts ? new Date(ts * 1000) : new Date();
                        const hh = String(date.getHours()).padStart(2, '0');
                        const mm = String(date.getMinutes()).padStart(2, '0');
                        const ss = String(date.getSeconds()).padStart(2, '0');
                        this.logEntries.push({
                            ts: `${hh}:${mm}:${ss}`,
                            level,
                            message,
                        });
                        if (this.logEntries.length > 200) this.logEntries.shift();
                        // Auto-scroll to bottom on next tick.
                        this.$nextTick(() => {
                            if (this.$refs.logBox) {
                                this.$refs.logBox.scrollTop = this.$refs.logBox.scrollHeight;
                            }
                        });
                    },
                    init() {
                        if (!this.uuid) return;
                        this.es = new EventSource(`/api/v1/menu-analyses/${this.uuid}/events`, { withCredentials: true });
                        this.es.onmessage = (e) => {
                            let parsed; try { parsed = JSON.parse(e.data); } catch (_) { return; }
                            const { event, data = {}, ts } = parsed;
                            this.handle(event, data, ts);
                        };
                        this.es.onerror = () => {
                            if (this.es && this.es.readyState === EventSource.CLOSED) {
                                this.error = 'Connection to event stream lost. Refresh the page to retry.';
                                this.es = null;
                            }
                        };
                    },
                    handle(event, data, ts) {
                        if (event === 'analysis.started') {
                            this.chunkTotal = Number(data.chunk_total) || 0;
                            this.setStage('queued', 'done', `${data.image_count || ''} image${data.image_count == 1 ? '' : 's'}`);
                            const chunkInfo = this.chunkTotal > 1 ? ` · ${this.chunkTotal} chunks` : '';
                            this.appendLog('info', `Analysis queued · ${data.image_count} image${data.image_count == 1 ? '' : 's'}${chunkInfo}`, ts);
                        } else if (event === 'analysis.preflight-start') {
                            this.advance('prep');
                            this.preflightTotal = Number(data.image_count) || 0;
                            this.preflightDone = 0;
                            this.setStage('prep', 'in_progress', `Preflight 0/${this.preflightTotal}`);
                            this.appendLog('info', `Preflight starting · ${data.image_count} image${data.image_count == 1 ? '' : 's'}`, ts);
                        } else if (event === 'analysis.preflight-image') {
                            this.preflightDone += 1;
                            this.setStage('prep', 'in_progress', `Preflight ${this.preflightDone}/${this.preflightTotal}`);
                            const rot = Number(data.rotation_cw) || 0;
                            const bbox = data.content_bbox ? ` · bbox ${data.content_bbox.map(n => n.toFixed(2)).join(',')}` : '';
                            const rotMsg = rot ? `rotate ${rot}° CW` : 'no rotation';
                            this.appendLog('ok', `Preflight image #${data.index} · ${rotMsg}${bbox} · quality=${data.quality}`, ts);
                        } else if (event === 'analysis.preprocess-start') {
                            this.preprocessTotal = Number(data.image_count) || 0;
                            this.preprocessDone = 0;
                            this.setStage('prep', 'in_progress', `Preprocessing 0/${this.preprocessTotal}`);
                            this.appendLog('info', `Preprocess starting · trim · deskew · WebP`, ts);
                        } else if (event === 'analysis.preprocess-image') {
                            this.preprocessDone += 1;
                            this.setStage('prep', 'in_progress', `Preprocessing ${this.preprocessDone}/${this.preprocessTotal}`);
                            this.appendLog('ok', `Preprocessed image #${data.index} · ${data.original_dims} → ${data.final_dims} · ${data.final_size_kb} KB`, ts);
                        } else if (event === 'analysis.vision-start') {
                            this.advance('vision');
                            const providers = (data.providers || []).join(' → ');
                            this.setStage('vision', 'in_progress', providers ? `Trying ${providers}` : '');
                            this.appendLog('info', `Vision LLM starting · provider chain: ${providers || 'n/a'}`, ts);
                        } else if (event === 'analysis.cascade-attempt') {
                            this.appendLog('info', `Trying tier ${data.tier} · ${data.provider}:${data.model}`, ts);
                        } else if (event === 'analysis.cascade-fallback') {
                            const sec = ((Number(data.duration_ms) || 0) / 1000).toFixed(1);
                            const remaining = Number(data.remaining_providers);
                            const tail = remaining > 0 ? ` · ${remaining} provider${remaining == 1 ? '' : 's'} remaining` : ' · no fallback left';
                            this.appendLog('warn', `Tier ${data.tier} ${data.provider}:${data.model} failed after ${sec}s — ${data.error}${tail}`, ts);
                        } else if (event === 'analysis.chunk-complete') {
                            this.advance('vision');
                            const done = Number(data.chunk_index) || 0;
                            const total = Number(data.chunk_total) || this.chunkTotal;
                            this.setStage('vision', 'in_progress', `${done}/${total} chunks done · ${data.provider || ''}`);
                            this.appendLog('ok', `Chunk ${done}/${total} done · ${data.provider || ''}:${data.model || ''} · tier ${data.tier ?? '?'}`, ts);
                        } else if (event === 'analysis.vision-complete') {
                            const sec = ((Number(data.duration_ms) || 0) / 1000).toFixed(1);
                            this.setStage('vision', 'done', `${data.provider || ''}:${data.model || ''} · ${sec}s · ${data.item_count || 0} items`);
                            this.advance('save');
                            const tokens = (data.input_tokens || data.output_tokens)
                                ? ` · ${data.input_tokens || '?'} in / ${data.output_tokens || '?'} out tokens`
                                : '';
                            this.appendLog('ok', `Vision LLM done · ${data.provider}:${data.model} · ${sec}s${tokens} · parsed ${data.item_count || 0} items`, ts);
                        } else if (event === 'analysis.menu-saved') {
                            this.setStage('save', 'done', `${data.section_count || 0} sections · ${data.item_count || 0} items`);
                            this.advance('crops');
                            this.appendLog('ok', `Menu saved · id=${data.menu_id} · ${data.section_count || 0} sections · ${data.item_count || 0} items`, ts);
                        } else if (event === 'analysis.crops-start') {
                            this.advance('crops');
                            this.setStage('crops', 'in_progress', `${data.items_with_bbox || 0} items to crop`);
                            this.appendLog('info', `Cropping ${data.items_with_bbox || 0} items with bbox …`, ts);
                        } else if (event === 'analysis.crops-complete') {
                            this.setStage('crops', 'done', `${data.items_cropped || 0} items cropped`);
                            this.appendLog('ok', `Cropping done · ${data.items_cropped || 0} items cropped`, ts);
                        } else if (event === 'analysis.completed') {
                            for (const s of this.stages) {
                                if (s.status === 'in_progress') s.status = 'done';
                                if (s.status === 'pending') s.status = 'skipped';
                            }
                            this.setStage('done', 'done', '');
                            this.appendLog('ok', `Analysis complete · menu_id=${data.menu_id || 'n/a'} · ${data.item_count || 0} items`, ts);
                            this.close();
                            $wire.call('checkAnalysisStatus');
                        } else if (event === 'analysis.failed') {
                            const failing = this.stages.find(s => s.status === 'in_progress')
                                         ?? this.stages.find(s => s.status === 'pending');
                            if (failing) failing.status = 'failed';
                            this.error = data.error || 'Analysis failed.';
                            this.appendLog('error', `Analysis failed · ${data.error || 'unknown'}`, ts);
                            this.close();
                            $wire.call('checkAnalysisStatus');
                        }
                    },
                    close() {
                        if (this.es) { this.es.close(); this.es = null; }
                    },
                    destroy() { this.close(); },
                };
            };
        </script>
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
