<!DOCTYPE html>
<html lang="{{ $lang }}" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>{{ $restaurant->translate('name', $lang) ?? $restaurant->name ?? 'Menu' }} — Menu</title>
    <meta name="theme-color" content="#f8fafc">
    <link rel="icon" href="data:,">
    <style>body{background:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;margin:0}</style>
    @vite(['resources/css/menu.css'])
</head>
<body>

    {{-- Navbar --}}
    <nav class="navbar" aria-label="Main navigation">
        <div class="container flex-between">
            <div class="flex gap-sm">
                <div>
                    <h1 class="restaurant-name">
                        {{ $restaurant->translate('name', $lang) ?? $restaurant->name ?? 'Menu' }}
                    </h1>
                </div>
            </div>
            <div class="flex gap-xs">
                @if(count($languages) > 1)
                    <div class="lang-switcher lang-dropdown" id="lang-switcher">
                        @php $currentLang = collect($languages)->firstWhere('code', $lang) ?? $languages[0]; @endphp
                        <button class="lang-current" id="lang-toggle">
                            <span class="lang-flag">{{ $currentLang['flag'] }}</span>
                            <span class="lang-arrow">▼</span>
                        </button>
                        <div class="lang-menu">
                            @foreach($languages as $language)
                                <a href="{{ request()->fullUrlWithQuery(['lang' => $language['code']]) }}"
                                   class="lang-option{{ $language['code'] === $lang ? ' lang-option-active' : '' }}">
                                    <span class="lang-flag">{{ $language['flag'] }}</span>
                                    <span>{{ $language['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
                <button class="theme-toggle" id="theme-toggle" aria-label="Toggle dark mode">
                    <svg class="ui-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>
            </div>
        </div>
    </nav>

    {{-- Search --}}
    <div class="container" style="padding-top:.75rem;padding-bottom:.5rem">
        <div id="search">
            <input type="text" id="search-input" class="search-input" placeholder="{{ $uiStrings['search'] }}">
        </div>
    </div>

    {{-- Category Tabs --}}
    @if($menu && $menu->sections->isNotEmpty())
        <div class="tabs-wrapper tabs-sticky">
            <div class="container">
                <div id="tabs" class="tabs">
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
    <main class="container" style="padding-top:1rem;padding-bottom:1rem">
        <div id="menu">
            @if($menu)
                @foreach($menu->sections as $section)
                    <section class="category-section" id="cat-{{ $section->id }}" data-cat-id="{{ $section->id }}">
                        <h2 class="category-title">
                            {{ $section->translate('name', $lang) ?? $section->name ?? '' }}
                        </h2>
                        <div class="menu-grid">
                            @foreach($section->items as $item)
                                @php
                                    $itemName = $item->translate('name', $lang) ?? $item->name ?? '';

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
                                <div class="menu-card" data-item-id="{{ $item->id }}">
                                    <div class="menu-card-visual">
                                   <img src="{{ Storage::url($item->image) }}" alt="" class="menu-card__image">
                                    </div>
                                    <div class="menu-card-body">
                                        <h3 class="menu-card-name">{{ $itemName }}</h3>
                                        <span class="menu-card-price">{{ number_format($displayPrice, 0, '', '.') }}{{ $currencySymbol }}</span>
                                    </div>
                                </div>
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

    <footer id="footer" class="container text-center text-muted text-sm" style="padding:2rem 1rem">
        <p>{{ $uiStrings['powered'] }}</p>
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
        ];
    @endphp
    <script>
        window.__ITEMS__ = @json($itemsJson);
        window.__UI__ = @json($uiStrings);
        window.__CONFIG__ = @json($config);
    </script>
    <script src="/icons/vanilla.js" data-src="/icons/food-icons.svg"></script>
    <script src="/js/menu.js"></script>
</body>
</html>
