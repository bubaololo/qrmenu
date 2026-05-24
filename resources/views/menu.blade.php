<!DOCTYPE html>
<html lang="{{ $lang }}" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    @php
        $restaurantName = $restaurant->name ?? 'Menu';
        $usedIcons = $menu
            ? $menu->sections->map(fn($s) => $s->icon?->name)->filter()->unique()->values()->all()
            : [];
        $iconSprite = \App\Support\FoodIcons::sprite($usedIcons);
    @endphp
    <title>{{ $restaurantName }} — Menu</title>
    <meta name="theme-color" content="#f4e6d0">
    <link rel="icon" href="data:,">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Unbounded:wght@400;500;600;700&display=swap">
    @if(\App\Support\InlineAssets::isHot())
        @vite(['resources/css/menu.css'])
    @else
        <style>{!! \App\Support\InlineAssets::viteCss('resources/css/menu.css') !!}</style>
    @endif
</head>
<body>

    {!! $iconSprite !!}

    <div class="grain" aria-hidden="true"></div>

    @php
        // Reuse the identifier the user came in with — if they hit /17/...
        // we keep `17` in the dropdown links instead of switching to uniqid.
        $menuIdentifier = $identifier ?? $restaurant->uniqid ?? $restaurant->id;
        $currentLocale = collect($locales ?? [])->firstWhere('code', $lang)
            ?? ['code' => $lang, 'label' => strtoupper($lang), 'flag' => "\u{1F310}"];
        $locationParts = collect([$restaurant->city, $heroInfo['address']])
            ->filter()
            ->values();
        $locationLabel = $locationParts->implode(', ');
        $hasMeta = $locationParts->isNotEmpty();
    @endphp

    @if($hasMeta)
        <div id="top" class="meta-strip">
            <div class="container meta-strip-row">
                @if($locationParts->isNotEmpty())
                    @if($heroInfo['mapsUrl'])
                        <a class="meta-item meta-loc" href="{{ $heroInfo['mapsUrl'] }}" target="_blank" rel="noopener" aria-label="{{ $locationLabel }} — open in Maps">
                            <svg class="meta-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span class="meta-loc-label">{{ $locationLabel }}</span>
                        </a>
                    @else
                        <span class="meta-item meta-loc">
                            <svg class="meta-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span class="meta-loc-label">{{ $locationLabel }}</span>
                        </span>
                    @endif
                @endif
            </div>
        </div>
    @endif

    <div class="container topbar-brandrow">
        <div class="topbar-brand-stack">
            <h1 class="topbar-brand font-display">{{ $restaurantName }}</h1>
            @if($heroInfo['todayHours'] !== null)
                <div class="topbar-brand-hours">{{ $heroInfo['todayHours'] }}</div>
            @endif
        </div>
    </div>

    <header class="topbar topbar-sticky" aria-label="Main navigation">
        <div class="container topbar-mainrow">
            <div id="search" class="search-field">
                <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="search-input" class="search-input" placeholder="{{ $uiStrings['search'] }}">
            </div>
            <div class="topbar-ctrls">
                <button class="theme-toggle" id="theme-toggle" aria-label="Toggle dark mode">
                    <svg class="ui-icon ui-icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    <svg class="ui-icon ui-icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                </button>
                <div class="lang-switcher lang-dropdown" id="lang-switcher">
                    <button class="lang-current" id="lang-toggle" aria-label="Language" aria-haspopup="listbox">
                        <span class="lang-flag">{{ $currentLocale['flag'] }}</span>
                        <span class="lang-code">{{ strtoupper($currentLocale['code']) }}</span>
                        <svg class="lang-arrow" width="10" height="10" viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 4l3 3 3-3"/></svg>
                    </button>
                    <div class="lang-menu" role="listbox">
                        @if(! empty($locales))
                            <div class="lang-search">
                                <input type="text" id="lang-search-input" class="lang-search-input"
                                       placeholder="{{ $uiStrings['langSearchPlaceholder'] ?? 'Search languages…' }}"
                                       autocomplete="off" spellcheck="false">
                            </div>
                            <div id="lang-all-list" class="lang-all-list">
                                <p class="lang-no-results" id="lang-no-results" hidden>{{ $uiStrings['langNoResults'] ?? 'No matches' }}</p>
                            </div>
                            {{-- Items live in a <template> until first dropdown open. <template> content is
                                 inert — native names (Русский, 中文, ...) don't trigger unicode-range font fetches
                                 until JS clones it into the live DOM on lang-toggle click. --}}
                            <template id="tpl-lang-options">
                                @foreach($locales as $loc)
                                    <a href="{{ route('menu.public', ['identifier' => $menuIdentifier, 'lang' => $loc['code']]) }}"
                                       data-code="{{ $loc['code'] }}"
                                       data-label="{{ strtolower($loc['label']) }}"
                                       data-native="{{ strtolower($loc['native'] ?? '') }}"
                                       class="lang-option lang-option--all{{ $loc['code'] === $lang ? ' lang-option-active' : '' }}{{ ! empty($loc['translated']) ? ' lang-option--ready' : '' }}"
                                       role="option"
                                       aria-selected="{{ $loc['code'] === $lang ? 'true' : 'false' }}">
                                        <span class="lang-flag">{{ $loc['flag'] }}</span>
                                        <span class="lang-name">
                                            {{ $loc['label'] }}
                                            @if(! empty($loc['native']) && $loc['native'] !== $loc['label'])
                                                <span class="lang-native">· {{ $loc['native'] }}</span>
                                            @endif
                                        </span>
                                        <span class="lang-code-tag">{{ strtoupper($loc['code']) }}</span>
                                    </a>
                                @endforeach
                            </template>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </header>

    @if($menu && $menu->sections->isNotEmpty())
        <div class="tabs-wrapper tabs-sticky">
            <div class="container">
                <div id="tabs" class="tabs" role="tablist">
                    <button class="tab tab-active" data-cat="all">{{ $uiStrings['all'] }}</button>
                    @foreach($menu->sections as $section)
                        <button class="tab" data-cat="{{ $section->id }}">
                            @if($section->icon?->name)
                                <svg class="tab-icon" width="16" height="16" aria-hidden="true"><use href="#{{ $section->icon->name }}"></use></svg>
                            @endif
                            <span>{{ $section->translate('name', $lang) ?? $section->name ?? '' }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <main class="container menu-main">
        <div id="menu">
            @if($menu)
                @foreach($menu->sections as $section)
                    @php
                        $sectionName = $section->translate('name', $lang) ?? $section->name ?? '';
                    @endphp
                    <section class="category-section" id="cat-{{ $section->id }}" data-cat-id="{{ $section->id }}">
                        <header class="category-header">
                            <h2 class="category-title font-display">
                                @if($section->icon?->name)
                                    <svg class="category-icon" width="28" height="28" aria-hidden="true"><use href="#{{ $section->icon->name }}"></use></svg>
                                @endif
                                <span>{{ $sectionName }}</span>
                            </h2>
                        </header>
                        <div class="menu-grid">
                            @foreach($section->items as $item)
                                @php
                                    $sourceLocale = $menu->source_locale ?? 'und';
                                    $itemName = $item->translate('name', $lang) ?? $item->name ?? '';
                                    $itemDesc = $item->translate('description', $lang);

                                    if ($item->variations->isNotEmpty()) {
                                        $firstOpt = $item->variations->first()->options->first();
                                        $displayPrice = $firstOpt
                                            ? (float) $item->price_value + (float) $firstOpt->price_adjust
                                            : (float) $item->price_value;
                                    } else {
                                        $displayPrice = (float) $item->price_value;
                                    }

                                    $hasVariants = $item->variations->flatMap(fn($v) => $v->options)->isNotEmpty();
                                    $hasOptions = $item->optionGroups->where('is_variation', false)->isNotEmpty();

                                    // Modal-only extras: fields menu.js needs that aren't visible in the card DOM.
                                    $extras = [];
                                    $fullDesc = $itemDesc ?? $item->translate('description', $sourceLocale);
                                    if ($fullDesc !== null && $fullDesc !== '') {
                                        $extras['description'] = $fullDesc;
                                    }
                                    $extras['price'] = (float) $item->price_value;
                                    $extras['orderable'] = (bool) $item->is_orderable;

                                    if ($hasVariants) {
                                        $variants = [];
                                        foreach ($item->variations as $g) {
                                            foreach ($g->options as $opt) {
                                                $variants[] = [
                                                    'name' => $opt->translate('name', $lang) ?? $opt->translate('name', $sourceLocale) ?? '',
                                                    'price' => (float) $item->price_value + (float) $opt->price_adjust,
                                                ];
                                            }
                                        }
                                        $extras['variants'] = $variants;
                                    }

                                    if ($hasOptions) {
                                        $options = [];
                                        foreach ($item->optionGroups->where('is_variation', false) as $g) {
                                            $options[] = [
                                                'id' => $g->id,
                                                'name' => $g->translate('name', $lang) ?? $g->translate('name', $sourceLocale) ?? '',
                                                'required' => $g->min_select > 0,
                                                'type' => $g->max_select === 1 ? 'single' : 'multiple',
                                                'max' => $g->max_select,
                                                'choices' => $g->options->map(fn ($o) => [
                                                    'id' => $o->id,
                                                    'name' => $o->translate('name', $lang) ?? $o->translate('name', $sourceLocale) ?? '',
                                                    'price' => (float) $o->price_adjust,
                                                ])->all(),
                                            ];
                                        }
                                        $extras['options'] = $options;
                                    }

                                    $shouldEmbedExtras = $hasVariants || $hasOptions || isset($extras['description']);
                                @endphp
                                <article class="menu-card{{ $item->image ? ' menu-card--image' : ' menu-card--noimage' }}" data-item-id="{{ $item->id }}">
                                    @if($item->image)
                                        <div class="menu-card-visual">
                                            <img src="{{ $item->thumb_url }}"
                                                 srcset="{{ $item->thumb_url }} 400w, {{ $item->image_url }} 800w"
                                                 sizes="(max-width: 767px) 50vw, (max-width: 1023px) 33vw, (max-width: 1279px) 25vw, 20vw"
                                                 alt="" class="menu-card__image" loading="lazy"
                                                 onload="this.classList.add('loaded')"
                                                 onerror="this.classList.add('loaded')">
                                        </div>
                                    @else
                                        <div class="menu-card-visual menu-card-visual--empty" aria-hidden="true">
                                            @if($section->icon?->name)
                                                <svg class="menu-card-icon-glyph" viewBox="0 0 24 24" aria-hidden="true"><use href="#{{ $section->icon->name }}"></use></svg>
                                            @else
                                                <span class="menu-card-monogram font-display">{{ mb_substr($itemName, 0, 1) }}</span>
                                            @endif
                                        </div>
                                    @endif
                                    <div class="menu-card-body">
                                        <h3 class="menu-card-name">{{ $itemName }}</h3>
                                        @if($itemDesc)
                                            <p class="menu-card-desc">{{ \Illuminate\Support\Str::limit($itemDesc, 90, '…') }}</p>
                                        @endif
                                        <div class="menu-card-foot">
                                            <span class="menu-card-price tabular">{{ number_format($displayPrice, 0, '', ' ') }}<span class="menu-card-currency">{{ $currencySymbol }}</span></span>
                                            @if($hasVariants || $hasOptions)
                                                <span class="menu-card-hint" aria-hidden="true">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    @if($shouldEmbedExtras)
                                        <script type="application/json" class="menu-card-extras">{!! json_encode($extras, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            @else
                <div class="no-results">
                    <p>{{ $uiStrings['noResults'] ?? 'No menu available' }}</p>
                </div>
            @endif

            <div id="no-results" class="no-results" hidden>
                <svg class="ui-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <span>{{ $uiStrings['noResults'] ?? 'No results' }}</span>
            </div>
        </div>
    </main>

    <footer id="footer" class="container menu-footer">
        <div class="footer-ornament" aria-hidden="true"></div>
        <p class="text-muted">{{ $uiStrings['powered'] }}</p>
    </footer>

    <div id="overlay" class="overlay"></div>

    <div id="item-sheet" class="bottom-sheet" role="dialog" aria-modal="true">
        <div id="item-sheet-content"></div>
    </div>

    <div id="cart-sheet" class="bottom-sheet cart-sheet" role="dialog" aria-modal="true">
        <div id="cart-sheet-content"></div>
    </div>

    <button id="cart-fab" class="cart-fab" aria-label="{{ $uiStrings['cart'] ?? 'Cart' }}">
        <span class="cart-fab-label">{{ $uiStrings['cart'] ?? 'Cart' }}</span>
        <span class="cart-fab-summary">
            <span class="cart-fab-total"></span>
            <span class="cart-fab-count"></span>
        </span>
    </button>

    <template id="tpl-item-sheet">
        <div class="bottom-sheet-handle"></div>
        <div class="sheet-visual">
            <button class="bottom-sheet-close" aria-label="{{ $uiStrings['close'] ?? 'Close' }}">&times;</button>
            <img class="sheet-image" alt="" hidden onload="this.classList.add('loaded')">
        </div>
        <div class="sheet-body">
            <h2 class="sheet-title"></h2>
            <p class="sheet-desc" hidden></p>
            <div class="sheet-variants" hidden>
                <p class="sheet-variants-label">{{ $uiStrings['chooseVariant'] ?? 'Choose' }}</p>
                <div class="variant-chips"></div>
            </div>
            <div class="sheet-options-container" hidden></div>
        </div>
        <div class="sheet-footer">
            <div class="sheet-controls">
                <div class="qty-control">
                    <button class="qty-btn qty-minus" data-delta="-1">&minus;</button>
                    <span class="qty-value">1</span>
                    <button class="qty-btn qty-plus" data-delta="1">+</button>
                </div>
                <button class="add-to-cart-btn"></button>
            </div>
        </div>
    </template>

    <template id="tpl-variant-chip">
        <button class="variant-chip"></button>
    </template>

    <template id="tpl-option-group">
        <div class="sheet-option-group">
            <div class="option-group-header">
                <span class="option-group-name"></span>
                <span class="option-tag"></span>
            </div>
            <div class="option-choices"></div>
        </div>
    </template>

    <template id="tpl-option-choice">
        <label class="option-choice">
            <span class="option-choice-check"></span>
            <span class="option-choice-name"></span>
            <span class="option-choice-price" hidden></span>
        </label>
    </template>

    <template id="tpl-cart-shell">
        <div class="cart-header">
            <h2 class="cart-title">{{ $uiStrings['cart'] ?? 'Cart' }}</h2>
            <button class="bottom-sheet-close" aria-label="{{ $uiStrings['close'] ?? 'Close' }}">&times;</button>
        </div>
        <div class="cart-items"></div>
        <div class="cart-footer">
            <div class="cart-total-row">
                <span class="cart-total-label">{{ $uiStrings['total'] ?? 'Total' }}</span>
                <span class="cart-total-value"></span>
            </div>
            <div class="cart-actions">
                <button class="cart-clear">{{ $uiStrings['clearCart'] ?? 'Clear' }}</button>
                <button class="cart-show-waiter">{{ $uiStrings['showWaiter'] ?? 'Show waiter' }}</button>
            </div>
        </div>
    </template>

    <template id="tpl-cart-item">
        <div class="cart-item">
            <div class="cart-item-delete">{{ $uiStrings['deleteItem'] ?? 'Delete' }}</div>
            <div class="cart-item-inner">
                <div class="cart-item-info">
                    <span class="cart-item-name"></span>
                    <span class="cart-item-variant cart-item-variant--variant" hidden></span>
                    <span class="cart-item-variant cart-item-variant--options" hidden></span>
                </div>
                <div class="cart-item-controls">
                    <div class="qty-control qty-control-sm">
                        <button class="qty-btn cart-qty-btn" data-delta="-1">&minus;</button>
                        <span class="qty-value"></span>
                        <button class="qty-btn cart-qty-btn" data-delta="1">+</button>
                    </div>
                    <span class="cart-item-total"></span>
                </div>
            </div>
        </div>
    </template>

    @php
        $config = [
            'currency' => $currencySymbol,
            'lang' => $lang,
            'primaryLang' => $primaryLang,
            'restaurantId' => $restaurant->id,
            'restaurantUniqid' => $restaurant->uniqid,
            'tableUniqid' => $tableUniqid ?? null,
            'menuId' => $menu?->id,
            'translationPending' => $translationPending ?? false,
            'translationLocale' => $translationLocale ?? null,
            'orderEndpoint' => '/api/v1/public/orders',
        ];
    @endphp
    <script>
        window.__UI__ = @json($uiStrings);
        window.__CONFIG__ = @json($config);
    </script>
    @if(\App\Support\InlineAssets::isHot())
        @vite(['resources/js/menu.js'])
    @else
        <script>{!! \App\Support\InlineAssets::viteJs('resources/js/menu.js') !!}</script>
    @endif

    @if(($translationPending ?? false) && $menu)
        <div id="translation-banner"
             class="translation-banner"
             role="status"
             aria-live="polite"
             style="position:fixed;left:50%;bottom:1rem;transform:translateX(-50%);z-index:90;background:rgba(20,20,20,.9);color:#fff;padding:.65rem 1rem;border-radius:999px;font-size:.85rem;display:flex;gap:.6rem;align-items:center;box-shadow:0 8px 30px rgba(0,0,0,.18);backdrop-filter:blur(10px)">
            <span class="translation-banner__spinner" aria-hidden="true"
                  style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin 0.9s linear infinite"></span>
            <span class="translation-banner__text" id="translation-banner-text">
                {{ $uiStrings['translating'] ?? 'Translating menu…' }}
            </span>
        </div>
        <style>@keyframes spin{to{transform:rotate(360deg)}}</style>
        <script>
            (function () {
                var menuId = window.__CONFIG__.menuId;
                var locale = window.__CONFIG__.translationLocale || window.__CONFIG__.lang;
                if (!menuId || !locale) return;

                var banner = document.getElementById('translation-banner');
                var bannerText = document.getElementById('translation-banner-text');
                if (!banner) return;

                var es = new EventSource('/api/v1/menus/' + menuId + '/translations/' + locale + '/events', { withCredentials: true });

                es.onmessage = function (e) {
                    try {
                        var parsed = JSON.parse(e.data);
                        var event = parsed.event;
                        var data = parsed.data || {};

                        if (event === 'translation.started') {
                            if (bannerText) bannerText.textContent = '0/' + (data.chunk_total || '?') + ' chunks…';
                        } else if (event === 'translation.chunk-complete') {
                            if (bannerText) bannerText.textContent =
                                (data.chunk_index || 0) + '/' + (data.chunk_total || '?') + ' chunks';
                        } else if (event === 'translation.completed') {
                            es.close();
                            if (bannerText) bannerText.textContent = 'Done · refreshing…';
                            setTimeout(function () { window.location.reload(); }, 800);
                        }
                    } catch (_) { /* ignore */ }
                };

                es.onerror = function () {
                    if (es.readyState === EventSource.CLOSED) {
                        if (bannerText) bannerText.textContent = 'Connection lost — refresh to retry.';
                    }
                };
            })();
        </script>
    @endif
    <script>
        (function () {
            var hero = document.querySelector('.hero');
            if (!hero || !('IntersectionObserver' in window)) return;
            var io = new IntersectionObserver(function (entries) {
                document.body.classList.toggle('scrolled', !entries[0].isIntersecting);
            }, { rootMargin: '-24px 0px 0px 0px', threshold: 0 });
            io.observe(hero);
        })();
    </script>
</body>
</html>
