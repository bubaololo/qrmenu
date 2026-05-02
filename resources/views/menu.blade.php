<!DOCTYPE html>
<html lang="{{ $lang }}" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    @php
        $restaurantName = $restaurant->translate('name', $lang) ?? $restaurant->name ?? 'Menu';
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
    <style>html,body{background:oklch(0.965 0.012 85);margin:0}</style>
    @vite(['resources/css/menu.css'])
</head>
<body>

    {{-- Inline icon sprite — only symbols referenced on this page --}}
    {!! $iconSprite !!}

    {{-- Grain overlay --}}
    <div class="grain" aria-hidden="true"></div>

    {{-- Navbar --}}
    @php
        // Reuse the identifier the user came in with — if they hit /17/...
        // we keep `17` in the dropdown links instead of switching to uniqid.
        $menuIdentifier = $identifier ?? $restaurant->uniqid ?? $restaurant->id;
        $currentLocale = collect($languages ?? [])->firstWhere('code', $lang)
            ?? ['code' => $lang, 'label' => strtoupper($lang), 'flag' => "\u{1F310}"];
        $availableCodes = collect($languages ?? [])->pluck('code')->all();
    @endphp
    <nav class="navbar" aria-label="Main navigation">
        <div class="container nav-row">
            <div class="brand-group">
                <span class="brand-name-compact font-display">{{ $restaurantName }}</span>
            </div>
            <div class="nav-ctrls">
                <div class="lang-switcher lang-dropdown" id="lang-switcher">
                    <button class="lang-current" id="lang-toggle" aria-label="Language" aria-haspopup="listbox">
                        <span class="lang-flag">{{ $currentLocale['flag'] }}</span>
                        <span class="lang-code">{{ strtoupper($currentLocale['code']) }}</span>
                        <svg class="lang-arrow" width="10" height="10" viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 4l3 3 3-3"/></svg>
                    </button>
                    <div class="lang-menu" role="listbox">
                        {{-- Already-translated locales --}}
                        @if(! empty($languages))
                            <div class="lang-section-title">{{ $uiStrings['langAvailable'] ?? 'Available' }}</div>
                            @foreach($languages as $language)
                                <a href="{{ route('menu.public', ['identifier' => $menuIdentifier, 'lang' => $language['code']]) }}"
                                   class="lang-option{{ $language['code'] === $lang ? ' lang-option-active' : '' }}"
                                   role="option"
                                   aria-selected="{{ $language['code'] === $lang ? 'true' : 'false' }}">
                                    <span class="lang-flag">{{ $language['flag'] }}</span>
                                    <span class="lang-name">{{ $language['label'] }}</span>
                                    <span class="lang-code-tag">{{ strtoupper($language['code']) }}</span>
                                </a>
                            @endforeach
                        @endif

                        {{-- Search + curated language list (translate on click) --}}
                        @if(! empty($allLocales))
                            <div class="lang-section-title lang-section-title--search">{{ $uiStrings['langTranslateTo'] ?? 'Translate to…' }}</div>
                            <div class="lang-search">
                                <input type="text" id="lang-search-input" class="lang-search-input"
                                       placeholder="{{ $uiStrings['langSearchPlaceholder'] ?? 'Search languages…' }}"
                                       autocomplete="off" spellcheck="false">
                            </div>
                            <div id="lang-all-list" class="lang-all-list">
                                @foreach($allLocales as $loc)
                                    @if(in_array($loc['code'], $availableCodes, true))
                                        @continue
                                    @endif
                                    <a href="{{ route('menu.public', ['identifier' => $menuIdentifier, 'lang' => $loc['code']]) }}"
                                       data-code="{{ $loc['code'] }}"
                                       data-label="{{ strtolower($loc['label']) }}"
                                       data-native="{{ strtolower($loc['native']) }}"
                                       class="lang-option lang-option--all">
                                        <span class="lang-flag">{{ $loc['flag'] }}</span>
                                        <span class="lang-name">
                                            {{ $loc['label'] }}
                                            @if($loc['native'] !== '' && $loc['native'] !== $loc['label'])
                                                <span class="lang-native">· {{ $loc['native'] }}</span>
                                            @endif
                                        </span>
                                        <span class="lang-code-tag">{{ strtoupper($loc['code']) }}</span>
                                    </a>
                                @endforeach
                                <p class="lang-no-results" id="lang-no-results" hidden>{{ $uiStrings['langNoResults'] ?? 'No matches' }}</p>
                            </div>
                            <script>
                                (function () {
                                    var input = document.getElementById('lang-search-input');
                                    var list = document.getElementById('lang-all-list');
                                    var emptyState = document.getElementById('lang-no-results');
                                    if (!input || !list) return;
                                    var items = Array.prototype.slice.call(list.querySelectorAll('a.lang-option'));

                                    input.addEventListener('input', function () {
                                        var q = input.value.trim().toLowerCase();
                                        var visibleCount = 0;
                                        for (var i = 0; i < items.length; i++) {
                                            var a = items[i];
                                            var hit = q === ''
                                                || (a.dataset.label || '').indexOf(q) !== -1
                                                || (a.dataset.native || '').indexOf(q) !== -1
                                                || (a.dataset.code || '').indexOf(q) === 0;
                                            a.hidden = !hit;
                                            if (hit) visibleCount++;
                                        }
                                        if (emptyState) emptyState.hidden = visibleCount > 0;
                                    });

                                    // Don't let the dropdown's outside-click handler eat clicks inside the input.
                                    input.addEventListener('click', function (e) { e.stopPropagation(); });
                                    input.addEventListener('keydown', function (e) {
                                        if (e.key === 'Escape') {
                                            input.value = '';
                                            input.dispatchEvent(new Event('input'));
                                        }
                                    });
                                })();
                            </script>
                        @endif
                    </div>
                </div>
                <button class="theme-toggle" id="theme-toggle" aria-label="Toggle dark mode">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>
            </div>
        </div>
    </nav>

    {{-- Hero --}}
    <header id="top" class="hero container">
        <article class="hero-card">
            <div class="hero-blob hero-blob-1" aria-hidden="true"></div>
            <div class="hero-blob hero-blob-2" aria-hidden="true"></div>
            @php
                $eyebrowParts = collect([$restaurant->city, $heroInfo['address']])
                    ->filter()
                    ->values();
            @endphp
            <div class="hero-inner">
                @if($heroInfo['statusLabel'] !== null || $eyebrowParts->isNotEmpty())
                    <span class="eyebrow">
                        @if($heroInfo['statusLabel'] !== null)
                            <span class="eyebrow-dot eyebrow-dot--{{ $heroInfo['isOpenNow'] ? 'open' : 'closed' }}"></span>
                            <span>{{ $heroInfo['statusLabel'] }}</span>
                        @endif
                        @foreach($eyebrowParts as $part)
                            @if(! $loop->first || $heroInfo['statusLabel'] !== null)
                                <span class="eyebrow-sep">·</span>
                            @endif
                            <span class="eyebrow-text">{{ $part }}</span>
                        @endforeach
                    </span>
                @endif
                <h1 class="hero-title font-display">{{ $restaurantName }}</h1>
                <dl class="hero-info">
                    @if($heroInfo['todayHours'] !== null)
                        <div class="hero-info-item">
                            <svg class="hero-info-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>
                            <div class="hero-info-text">
                                <dt>{{ $uiStrings['hoursToday'] }}</dt>
                                <dd>{{ $heroInfo['todayHours'] }}</dd>
                            </div>
                        </div>
                    @endif
                    <div class="hero-info-item">
                        <svg class="hero-info-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M14.5 9.5a2.8 2.8 0 0 0-2.5-1.5c-1.7 0-3 1-3 2.4 0 1.3 1 1.9 2.6 2.3l1 .25c1.6.4 2.6 1 2.6 2.3 0 1.4-1.3 2.4-3 2.4a2.8 2.8 0 0 1-2.6-1.5"/><path d="M12 6.5v11"/></svg>
                        <div class="hero-info-text">
                            <dt>{{ $uiStrings['currency'] }}</dt>
                            <dd>{{ $currencySymbol }} · {{ $currencyCode }}</dd>
                        </div>
                    </div>
                </dl>
            </div>
        </article>
    </header>

    {{-- Search --}}
    <div class="container search-wrap">
        <div id="search" class="search-field">
            <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="search-input" class="search-input" placeholder="{{ $uiStrings['search'] }}">
        </div>
    </div>

    {{-- Category Tabs --}}
    @if($menu && $menu->sections->isNotEmpty())
        <div class="tabs-wrapper tabs-sticky">
            <div class="container">
                <div id="tabs" class="tabs" role="tablist">
                    <button class="tab tab-active" data-cat="all">{{ $uiStrings['all'] }}</button>
                    @foreach($menu->sections as $section)
                        <button class="tab" data-cat="{{ $section->id }}">
                            {{ $section->translate('name', $lang) ?? $section->name ?? '' }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Menu Content --}}
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
                                    $hasOptions = $item->optionGroups->isNotEmpty();
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
        </div>
    </main>

    <footer id="footer" class="container menu-footer">
        <div class="footer-ornament" aria-hidden="true"></div>
        <p class="text-muted">{{ $uiStrings['powered'] }}</p>
    </footer>

    {{-- Overlay --}}
    <div id="overlay" class="overlay"></div>

    {{-- Item Detail Bottom Sheet --}}
    <div id="item-sheet" class="bottom-sheet" role="dialog" aria-modal="true">
        <div id="item-sheet-content"></div>
    </div>

    {{-- Cart Bottom Sheet --}}
    <div id="cart-sheet" class="bottom-sheet cart-sheet" role="dialog" aria-modal="true">
        <div id="cart-sheet-content"></div>
    </div>

    {{-- Cart FAB --}}
    <button id="cart-fab" class="cart-fab" aria-label="Cart"></button>

    {{-- Data for JS interactivity --}}
    @php
        $config = [
            'currency' => $currencySymbol,
            'lang' => $lang,
            'primaryLang' => $primaryLang,
            'restaurantId' => $restaurant->id,
            'menuId' => $menu?->id,
            'translationPending' => $translationPending ?? false,
            'translationLocale' => $translationLocale ?? null,
        ];
    @endphp
    <script>
        window.__ITEMS__ = @json($itemsJson);
        window.__UI__ = @json($uiStrings);
        window.__CONFIG__ = @json($config);
    </script>
    <script src="/js/menu.js"></script>

    {{-- Live translation banner: shows up only when this request triggered a
         pending translation. Subscribes to SSE; reloads on translation.completed. --}}
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
