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
    <meta name="theme-color" content="#ffffff">
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

        // Collapsible restaurant-info panel: only render rows that are filled.
        $infoPhone = $heroInfo['phone'] ?? null;
        $infoHours = $heroInfo['todayHours'] ?? null;
        $hasInfo = $hasMeta || ! empty($infoPhone) || $infoHours !== null;
        // Strip everything but digits and a leading + for the tel: target.
        $telHref = $infoPhone ? preg_replace('/(?!^\+)[^\d]/', '', $infoPhone) : null;
    @endphp

    @if($restaurant->image_url)
        <div class="hero-banner">
            <img src="{{ $restaurant->thumb_url }}"
                 srcset="{{ $restaurant->thumb_url }} 800w, {{ $restaurant->image_url }} 1600w"
                 sizes="(min-width: 1280px) 1120px, (min-width: 1024px) 980px, 100vw"
                 alt="" class="hero-banner__img" fetchpriority="high" decoding="async"
                 onload="this.classList.add('loaded')"
                 onerror="this.classList.add('loaded')">
        </div>
        @if($restaurant->logo_url)
            <div class="container hero-logo-row">
                <img src="{{ $restaurant->logo_thumb_url }}" alt="" class="hero-logo">
            </div>
        @endif
    @endif

    <div id="top" class="container topbar-brandrow">
        @if($restaurant->logo_url && ! $restaurant->image_url)
            <img src="{{ $restaurant->logo_thumb_url }}" alt="" class="hero-logo hero-logo--inline">
        @endif
        <h1 class="topbar-brand font-display">{{ $restaurantName }}</h1>
        @if($hasInfo)
            <button type="button" class="info-toggle" id="info-toggle"
                    aria-expanded="false" aria-controls="info-panel"
                    aria-label="{{ $uiStrings['details'] ?? 'Details' }}">
                <svg class="info-toggle-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/></svg>
            </button>
        @endif
    </div>

    @if($hasInfo)
        <div class="info-panel" id="info-panel" role="region" aria-label="{{ $uiStrings['details'] ?? 'Details' }}">
            <div class="info-panel-clip">
                <div class="container info-panel-content">
                    @if($infoHours !== null)
                        <div class="info-row">
                            <svg class="info-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                            <span class="info-text">{{ $infoHours }}</span>
                        </div>
                    @endif

                    @if($hasMeta)
                        <div class="info-row">
                            <svg class="info-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            @if($heroInfo['mapsUrl'])
                                <a class="info-text info-link" href="{{ $heroInfo['mapsUrl'] }}" target="_blank" rel="noopener" aria-label="{{ $locationLabel }} — Maps">{{ $locationLabel }}</a>
                            @else
                                <span class="info-text">{{ $locationLabel }}</span>
                            @endif
                            <button type="button" class="info-copy" data-copy="{{ $locationLabel }}" aria-label="{{ $uiStrings['copy'] ?? 'Copy' }}">
                                <svg class="info-copy-icon info-copy-icon--copy" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
                                <svg class="info-copy-icon info-copy-icon--done" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                            </button>
                        </div>
                    @endif

                    @if($infoPhone)
                        <div class="info-row">
                            <svg class="info-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            <a class="info-text info-link" href="tel:{{ $telHref }}">{{ $infoPhone }}</a>
                            <button type="button" class="info-copy" data-copy="{{ $infoPhone }}" aria-label="{{ $uiStrings['copy'] ?? 'Copy' }}">
                                <svg class="info-copy-icon info-copy-icon--copy" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
                                <svg class="info-copy-icon info-copy-icon--done" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <header class="topbar topbar-sticky" aria-label="Main navigation">
        <div class="container topbar-mainrow">
            <button class="cat-chip" id="cat-chip" aria-haspopup="dialog"
                    @if(! ($menu && $menu->sections->isNotEmpty())) hidden @endif>
                <span class="cat-chip-label" id="cat-chip-label">{{ $uiStrings['all'] }}</span>
                <svg class="cat-chip-arrow" width="10" height="10" viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 4l3 3 3-3"/></svg>
            </button>
            <div id="search" class="search-field">
                <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="search-input" class="search-input" placeholder="{{ $uiStrings['search'] }}">
                <button class="search-cancel" id="search-close" aria-label="{{ $uiStrings['close'] ?? 'Close' }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="topbar-ctrls">
                <button class="icon-btn" id="search-open" aria-label="{{ $uiStrings['search'] }}">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </button>
                <button class="theme-toggle icon-btn" id="theme-toggle" aria-label="Toggle dark mode">
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

    <main class="container menu-main">
        <div id="menu">
            @if($menu)
                @foreach($menu->sections as $section)
                    @php
                        $sectionName = $section->translate('name', $lang) ?? $section->name ?? '';
                        // Layout by photo share: Grab-style media rows when at least half the
                        // items have photos; paper-menu rows otherwise (stray photos = thumbs)
                        $photoCount = $section->items->filter(fn ($i) => $i->image)->count();
                        $useMedia = $photoCount > 0 && $photoCount * 2 >= $section->items->count();
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
                        <div class="menu-grid{{ $useMedia ? ' menu-grid--media' : ' menu-grid--list' }}">
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
                                <article class="menu-card{{ $item->image ? '' : ' menu-card--noimage' }}" data-item-id="{{ $item->id }}"@if($item->starred) data-starred="1"@endif role="button" tabindex="0">
                                    @if($item->image)
                                        @php
                                            // Thumbs render at a fixed width: 104px in media rows, 56px in
                                            // paper lists — `sizes` must match or dpr3 phones overfetch 800w.
                                            // First items of the first section are above the fold: eager-load
                                            // them so LCP doesn't wait for the lazy decoder.
                                            $aboveTheFold = $loop->parent->first && $loop->index < 4;
                                        @endphp
                                        <div class="menu-card-visual">
                                            <img src="{{ $item->thumb_url }}"
                                                 srcset="{{ $item->thumb_url }} 400w, {{ $item->image_url }} 1024w"
                                                 sizes="{{ $useMedia ? '104px' : '56px' }}"
                                                 data-full="{{ $item->image_url }}"
                                                 alt="" class="menu-card__image"
                                                 loading="{{ $aboveTheFold ? 'eager' : 'lazy' }}"
                                                 @if($aboveTheFold) fetchpriority="high" @endif
                                                 decoding="async"
                                                 onload="this.classList.add('loaded')"
                                                 onerror="this.classList.add('loaded')">
                                        </div>
                                    @endif
                                    <div class="menu-card-body">
                                        <h3 class="menu-card-name">{{ $itemName }}@if($item->starred)<svg class="menu-card-star" width="13" height="13" viewBox="0 0 24 24" fill="currentColor" role="img" aria-label="{{ $uiStrings['recommended'] ?? 'Recommended' }}"><path d="M12 2l2.92 6.26 6.58.57-5 4.35 1.5 6.45L12 16.2l-6 3.43 1.5-6.45-5-4.35 6.58-.57L12 2z"/></svg>@endif</h3>
                                        @if($itemDesc)
                                            <p class="menu-card-desc">{{ \Illuminate\Support\Str::limit($itemDesc, 90, '…') }}</p>
                                        @endif
                                        <div class="menu-card-foot">
                                            <span class="menu-card-price tabular">{{ number_format($displayPrice, 0, '', ' ') }}<span class="menu-card-currency">{{ $currencySymbol }}</span></span>
                                            <svg class="menu-card-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
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

    <div id="overlay" class="overlay"></div>

    <div id="item-sheet" class="bottom-sheet" role="dialog" aria-modal="true">
        <div id="item-sheet-content"></div>
    </div>

    <div id="cart-sheet" class="bottom-sheet cart-sheet" role="dialog" aria-modal="true">
        <div id="cart-sheet-content"></div>
    </div>

    @if($menu && $menu->sections->isNotEmpty())
        <div id="cat-sheet" class="bottom-sheet" role="dialog" aria-modal="true" aria-label="{{ $uiStrings['all'] }}">
            <div class="bottom-sheet-handle"></div>
            <nav class="cat-list">
                <button class="cat-option cat-option-active" data-cat="all">
                    <span class="cat-option-name">{{ $uiStrings['all'] }}</span>
                </button>
                @foreach($menu->sections as $section)
                    <button class="cat-option" data-cat="{{ $section->id }}">
                        @if($section->icon?->name)
                            <svg class="cat-option-icon" width="18" height="18" aria-hidden="true"><use href="#{{ $section->icon->name }}"></use></svg>
                        @endif
                        <span class="cat-option-name">{{ $section->translate('name', $lang) ?? $section->name ?? '' }}</span>
                    </button>
                @endforeach
            </nav>
        </div>
    @endif

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
            <button class="bottom-sheet-close" aria-label="{{ $uiStrings['close'] ?? 'Close' }}">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
            <img class="sheet-image" alt="" hidden onload="this.classList.add('loaded')">
        </div>
        <div class="sheet-body">
            <h2 class="sheet-title"></h2>
            <div class="sheet-badges badges" hidden>
                <span class="badge badge-popular">{{ $uiStrings['recommended'] ?? 'Recommended' }}</span>
            </div>
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
            <button class="bottom-sheet-close" aria-label="{{ $uiStrings['close'] ?? 'Close' }}">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
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
             style="position:fixed;left:50%;bottom:1rem;transform:translateX(-50%);z-index:90;background:rgba(20,20,20,.92);color:#fff;padding:.7rem 1rem;border-radius:16px;font-size:.85rem;display:flex;flex-direction:column;gap:.55rem;min-width:210px;max-width:80vw;box-shadow:0 8px 30px rgba(0,0,0,.22);backdrop-filter:blur(10px)">
            <div style="display:flex;gap:.55rem;align-items:center">
                <span class="translation-banner__spinner" aria-hidden="true"
                      style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin 0.9s linear infinite;flex:none"></span>
                <span class="translation-banner__text" id="translation-banner-text" style="flex:1">
                    {{ $uiStrings['translating'] ?? 'Translating menu…' }}
                </span>
            </div>
            <div id="translation-progress" style="display:flex;gap:4px;height:6px">
                @for($i = 0; $i < max(1, (int) ($translationChunkTotal ?? 1)); $i++)
                    <div class="tp-seg" style="flex:1;height:100%;border-radius:3px;background:rgba(255,255,255,.22);transition:background .35s ease"></div>
                @endfor
            </div>
        </div>
        <style>@keyframes spin{to{transform:rotate(360deg)}}</style>
        <script>
            window.__REVERB__ = {
                key: @json(config('broadcasting.connections.reverb.key')),
                host: @json(config('broadcasting.connections.reverb.options.host')),
                port: @json((int) config('broadcasting.connections.reverb.options.port', 443)),
                scheme: @json(config('broadcasting.connections.reverb.options.scheme', 'https')),
            };
            // Minimal Pusher-protocol client for the public `menu-translation.*`
            // channel (no auth needed) over Laravel Reverb. Avoids bundling
            // laravel-echo/pusher-js into this server-rendered page.
            (function () {
                var cfg = window.__CONFIG__ || {};
                var R = window.__REVERB__ || {};
                var menuId = cfg.menuId;
                var locale = cfg.translationLocale || cfg.lang;
                if (!menuId || !locale || !R.key || !R.host) return;

                var banner = document.getElementById('translation-banner');
                var bannerText = document.getElementById('translation-banner-text');
                var progressEl = document.getElementById('translation-progress');
                var channel = 'menu-translation.' + menuId + '.' + locale;

                // Render `total` progress segments (rebuild only if the count changed
                // from the server-rendered estimate).
                function renderSegments(total) {
                    if (!progressEl || !total || total < 1 || progressEl.children.length === total) return;
                    progressEl.innerHTML = '';
                    for (var i = 0; i < total; i++) {
                        var seg = document.createElement('div');
                        seg.style.cssText = 'flex:1;height:100%;border-radius:3px;background:rgba(255,255,255,.22);transition:background .35s ease';
                        progressEl.appendChild(seg);
                    }
                }
                // Fill the first `done` segments (cumulative — self-corrects on reload).
                function fillSegments(done) {
                    if (!progressEl) return;
                    var segs = progressEl.children;
                    for (var i = 0; i < segs.length; i++) {
                        segs[i].style.background = i < done ? '#fff' : 'rgba(255,255,255,.22)';
                    }
                }
                function progressText(done, total) {
                    if (bannerText) bannerText.textContent = done + '/' + (total || '?') + ' chunks';
                }

                // Safety net: if the 'completed' event is ever missed (socket
                // dropped, or the translation finished before we connected),
                // reload once so the finished translation still shows — instead of
                // the banner hanging. A reload won't re-arm the socket unless a
                // translation is genuinely still running.
                var settled = false;
                var fallbackTimer = setTimeout(function () {
                    if (!settled) window.location.reload();
                }, 90000);

                var wsProto = R.scheme === 'https' ? 'wss' : 'ws';
                var url = wsProto + '://' + R.host + ':' + R.port + '/app/' + R.key
                    + '?protocol=7&client=js&version=8&flash=false';

                var ws;
                try { ws = new WebSocket(url); } catch (_) { return; }

                ws.onmessage = function (e) {
                    var msg;
                    try { msg = JSON.parse(e.data); } catch (_) { return; }
                    if (msg.event === 'pusher:ping') {
                        ws.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
                        return;
                    }
                    if (msg.event === 'pusher:connection_established') {
                        ws.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel: channel } }));
                        return;
                    }
                    var data = {};
                    try { data = typeof msg.data === 'string' ? JSON.parse(msg.data) : (msg.data || {}); } catch (_) {}
                    if (msg.event === 'translation.started') {
                        renderSegments(data.chunk_total);
                        progressText(0, data.chunk_total);
                    } else if (msg.event === 'translation.chunk-complete') {
                        renderSegments(data.chunk_total);
                        fillSegments(data.chunk_index || 0);
                        progressText(data.chunk_index || 0, data.chunk_total);
                    } else if (msg.event === 'translation.completed') {
                        settled = true;
                        clearTimeout(fallbackTimer);
                        fillSegments(progressEl ? progressEl.children.length : 0);
                        try { ws.close(); } catch (_) {}
                        if (bannerText) bannerText.textContent = 'Done · refreshing…';
                        setTimeout(function () { window.location.reload(); }, 700);
                    }
                };

                ws.onerror = function () {
                    // Don't leave a stuck banner; the fallback timer will reload.
                    if (banner) banner.style.display = 'none';
                };
            })();
        </script>
    @endif
</body>
</html>
