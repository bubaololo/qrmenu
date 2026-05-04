<?php

namespace App\Http\Controllers;

use App\Jobs\TranslateMenuJob;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\Translation;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Matriphe\ISO639\ISO639;

class MenuPageController extends Controller
{
    public function showTable(string $restaurant, string $table, ?string $lang = null): View
    {
        $tableModel = DiningTable::where('uniqid', $table)->firstOrFail();

        $restaurantModel = is_numeric($restaurant)
            ? Restaurant::findOrFail((int) $restaurant)
            : Restaurant::where('uniqid', $restaurant)->firstOrFail();

        abort_unless($tableModel->zone->restaurant_id === $restaurantModel->id, 404);

        return $this->show($restaurantModel->uniqid, $lang, $tableModel->uniqid);
    }

    public function show(string $identifier, ?string $lang = null, ?string $tableUniqid = null): View
    {
        $restaurant = is_numeric($identifier)
            ? Restaurant::findOrFail((int) $identifier)
            : Restaurant::where('uniqid', $identifier)->firstOrFail();

        // First pass: load the menu metadata so we know source_locale before loading
        // the (potentially huge) translations chain.
        $restaurant->load(['menu']);

        $menu = $restaurant->menu;
        $primaryLang = $restaurant->primary_language ?? 'en';

        // Normalize: null, 'mixed', or invalid ISO-639-1 code → default to primaryLang
        $iso = new ISO639;
        if ($lang === null || $lang === 'mixed' || ($lang !== $primaryLang && $iso->languageByCode1($lang) === '')) {
            $lang = $primaryLang;
        }

        // Scope translation eager-loads to (a) the requested locale, (b) the menu's
        // source_locale where MenuItem/Section/etc. initials live, and (c) the
        // restaurant's primary_language where Restaurant/Zone initials live. This
        // is a superset that guarantees translate()'s initial-fallback always finds
        // its source row in the loaded collection.
        $sourceLocale = $menu?->source_locale ?? $primaryLang;
        $locales = array_values(array_unique(array_filter([$lang, $sourceLocale, $primaryLang])));
        $scope = fn ($q) => $q->whereIn('locale', $locales);

        $activeSection = fn ($q) => $q->where('is_active', true);
        $activeItem = fn ($q) => $q->where('is_active', true);

        $restaurant->load([
            'translations' => $scope,
            'menu.sections' => $activeSection,
            'menu.sections.icon',
            'menu.sections.translations' => $scope,
            'menu.sections.items' => $activeItem,
            'menu.sections.items.translations' => $scope,
            'menu.sections.items.variations.options.translations' => $scope,
            'menu.sections.items.optionGroups.translations' => $scope,
            'menu.sections.items.optionGroups.options.translations' => $scope,
        ]);

        $menu = $restaurant->menu;

        // On-demand translation: if locale not initial and no translations exist, trigger LLM
        $translationPending = false;
        $requestedLang = $lang;
        if ($menu && $lang !== ($menu->source_locale ?? $primaryLang)) {
            $translationPending = $this->ensureTranslations($restaurant, $menu, $lang);
            $menu = $restaurant->menu; // refresh after potential translation
        }

        $languages = $this->getAvailableLanguages($restaurant, $menu, $lang);

        if (! collect($languages)->pluck('code')->contains($lang)) {
            $lang = $primaryLang;
        }

        // The page renders fallback strings while translation chunks crunch;
        // the SSE banner needs the originally-requested locale so it can
        // subscribe to *that* topic and reload the page when chunks land.
        $translationLocale = $translationPending ? $requestedLang : null;

        $itemsJson = $this->buildItemsJson($menu, $lang);
        $currencyCode = strtoupper($restaurant->currency ?? 'USD');
        $currencySymbol = $this->getCurrencySymbol($currencyCode);
        $uiStrings = $this->getUiStrings($lang);
        $heroInfo = $this->buildHeroInfo($restaurant, $uiStrings);

        $locales = $this->buildLocaleList($languages, $this->getCommonLanguages());

        return view('menu', compact(
            'restaurant',
            'menu',
            'lang',
            'itemsJson',
            'currencyCode',
            'currencySymbol',
            'primaryLang',
            'uiStrings',
            'heroInfo',
            'translationPending',
            'translationLocale',
            'locales',
            'identifier',
            'tableUniqid',
        ));
    }

    /**
     * Build the flat language list rendered in the public switcher.
     *
     * Sort order:
     *   1. Already-translated locales first
     *   2. Primary tier:   vi → en → zh → ko → ru → fr (curated for SEA tourist mix)
     *   3. Secondary tier: ja → th → id → ms → de → es → it → nl
     *      (next-most-likely demand: NE Asia + SEA neighbours + Western Europe)
     *   4. Alphabetical by English label for the long tail
     *
     * @param  list<array{code: string, label: string, flag: string}>  $languages  Already-translated locales
     * @param  list<array{code: string, label: string, native: string, flag: string}>  $allLocales  Curated full list
     * @return list<array{code: string, label: string, native: string, flag: string, translated: bool}>
     */
    private function buildLocaleList(array $languages, array $allLocales): array
    {
        $translatedCodes = collect($languages)->pluck('code')->all();
        $byCode = [];

        foreach ($allLocales as $loc) {
            $byCode[$loc['code']] = $loc + [
                'translated' => in_array($loc['code'], $translatedCodes, true),
            ];
        }

        // Translated locales not in the curated list (e.g. exotic source_locale)
        // still need to show up at the top.
        foreach ($languages as $lang) {
            if (! isset($byCode[$lang['code']])) {
                $byCode[$lang['code']] = [
                    'code' => $lang['code'],
                    'label' => $lang['label'],
                    'native' => '',
                    'flag' => $lang['flag'],
                    'translated' => true,
                ];
            }
        }

        $priority = [
            'vi', 'en', 'zh', 'ko', 'ru', 'fr',
            'ja', 'th', 'id', 'ms', 'de', 'es', 'it', 'nl',
        ];
        $rows = array_values($byCode);

        usort($rows, function ($a, $b) use ($priority) {
            $byTranslated = ($b['translated'] <=> $a['translated']);
            if ($byTranslated !== 0) {
                return $byTranslated;
            }

            $pa = array_search($a['code'], $priority, true);
            $pb = array_search($b['code'], $priority, true);
            $pa = $pa === false ? PHP_INT_MAX : $pa;
            $pb = $pb === false ? PHP_INT_MAX : $pb;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return strcmp($a['label'], $b['label']);
        });

        return $rows;
    }

    /**
     * Compose the hero header data: today's opening window, current open/closed
     * status, and the address with a maps link.
     *
     * @param  array<string, string>  $uiStrings
     * @return array{
     *     address: ?string,
     *     mapsUrl: ?string,
     *     todayHours: ?string,
     *     isOpenNow: ?bool,
     *     statusLabel: ?string,
     * }
     */
    private function buildHeroInfo(Restaurant $restaurant, array $uiStrings): array
    {
        $hours = is_array($restaurant->opening_hours) ? $restaurant->opening_hours : null;
        [$todayHours, $isOpenNow] = $this->resolveTodayHours($hours, $uiStrings);

        $statusLabel = match ($isOpenNow) {
            true => $uiStrings['openNow'] ?? 'Open',
            false => $uiStrings['closedNow'] ?? 'Closed',
            default => null,
        };

        return [
            'address' => $restaurant->address,
            'mapsUrl' => $restaurant->google_maps_url,
            'todayHours' => $todayHours,
            'isOpenNow' => $isOpenNow,
            'statusLabel' => $statusLabel,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $hours
     * @param  array<string, string>  $uiStrings
     * @return array{0: ?string, 1: ?bool} [labelForToday, isOpenNow]
     */
    private function resolveTodayHours(?array $hours, array $uiStrings): array
    {
        if ($hours === null) {
            return [null, null];
        }

        if (! empty($hours['is_24_7'])) {
            return [$uiStrings['open24h'] ?? '24 hours', true];
        }

        $periods = is_array($hours['periods'] ?? null) ? $hours['periods'] : [];
        if ($periods === []) {
            return [null, null];
        }

        $todayCode = strtolower(now()->format('D')); // mon, tue, wed, ...

        $todays = [];
        foreach ($periods as $period) {
            $days = array_map('strtolower', $period['days'] ?? []);
            if (in_array($todayCode, $days, true) && ! empty($period['open']) && ! empty($period['close'])) {
                $todays[] = ['open' => (string) $period['open'], 'close' => (string) $period['close']];
            }
        }

        if ($todays === []) {
            return [$uiStrings['closedToday'] ?? 'Closed today', false];
        }

        usort($todays, fn (array $a, array $b): int => strcmp($a['open'], $b['open']));

        $now = now()->format('H:i');
        $isOpenNow = false;
        foreach ($todays as $period) {
            if ($period['close'] >= $period['open']) {
                if ($now >= $period['open'] && $now < $period['close']) {
                    $isOpenNow = true;
                    break;
                }
            } else {
                // Overnight period (e.g. 18:00–02:00)
                if ($now >= $period['open'] || $now < $period['close']) {
                    $isOpenNow = true;
                    break;
                }
            }
        }

        $label = implode(', ', array_map(
            fn (array $p): string => $p['open'].'–'.$p['close'],
            $todays,
        ));

        return [$label, $isOpenNow];
    }

    /**
     * Curated set of high-traffic languages an LLM translates well — major
     * world languages and tourist languages used in restaurant menus.
     * Excludes ISO 639-1 codes the LLM doesn't reliably handle (smaller
     * languages with sparse training data).
     *
     * @return list<array{code: string, label: string, native: string, flag: string}>
     */
    private function getCommonLanguages(): array
    {
        $rows = [
            ['en', 'English',     'English',         "\u{1F1EC}\u{1F1E7}"],
            ['ru', 'Russian',     'Русский',         "\u{1F1F7}\u{1F1FA}"],
            ['vi', 'Vietnamese',  'Tiếng Việt',      "\u{1F1FB}\u{1F1F3}"],
            ['zh', 'Chinese',     '中文',            "\u{1F1E8}\u{1F1F3}"],
            ['ja', 'Japanese',    '日本語',          "\u{1F1EF}\u{1F1F5}"],
            ['ko', 'Korean',      '한국어',          "\u{1F1F0}\u{1F1F7}"],
            ['fr', 'French',      'Français',        "\u{1F1EB}\u{1F1F7}"],
            ['es', 'Spanish',     'Español',         "\u{1F1EA}\u{1F1F8}"],
            ['de', 'German',      'Deutsch',         "\u{1F1E9}\u{1F1EA}"],
            ['it', 'Italian',     'Italiano',        "\u{1F1EE}\u{1F1F9}"],
            ['pt', 'Portuguese',  'Português',       "\u{1F1F5}\u{1F1F9}"],
            ['nl', 'Dutch',       'Nederlands',      "\u{1F1F3}\u{1F1F1}"],
            ['ar', 'Arabic',      'العربية',         "\u{1F1F8}\u{1F1E6}"],
            ['hi', 'Hindi',       'हिन्दी',           "\u{1F1EE}\u{1F1F3}"],
            ['tr', 'Turkish',     'Türkçe',          "\u{1F1F9}\u{1F1F7}"],
            ['pl', 'Polish',      'Polski',          "\u{1F1F5}\u{1F1F1}"],
            ['uk', 'Ukrainian',   'Українська',      "\u{1F1FA}\u{1F1E6}"],
            ['cs', 'Czech',       'Čeština',         "\u{1F1E8}\u{1F1FF}"],
            ['ro', 'Romanian',    'Română',          "\u{1F1F7}\u{1F1F4}"],
            ['hu', 'Hungarian',   'Magyar',          "\u{1F1ED}\u{1F1FA}"],
            ['el', 'Greek',       'Ελληνικά',        "\u{1F1EC}\u{1F1F7}"],
            ['sv', 'Swedish',     'Svenska',         "\u{1F1F8}\u{1F1EA}"],
            ['no', 'Norwegian',   'Norsk',           "\u{1F1F3}\u{1F1F4}"],
            ['da', 'Danish',      'Dansk',           "\u{1F1E9}\u{1F1F0}"],
            ['fi', 'Finnish',     'Suomi',           "\u{1F1EB}\u{1F1EE}"],
            ['th', 'Thai',        'ไทย',             "\u{1F1F9}\u{1F1ED}"],
            ['id', 'Indonesian',  'Bahasa Indonesia', "\u{1F1EE}\u{1F1E9}"],
            ['ms', 'Malay',       'Bahasa Melayu',   "\u{1F1F2}\u{1F1FE}"],
            ['kk', 'Kazakh',      'Қазақша',         "\u{1F1F0}\u{1F1FF}"],
            ['ky', 'Kyrgyz',      'Кыргызча',        "\u{1F1F0}\u{1F1EC}"],
            ['uz', 'Uzbek',       'Oʻzbek',          "\u{1F1FA}\u{1F1FF}"],
            ['az', 'Azerbaijani', 'Azərbaycanca',    "\u{1F1E6}\u{1F1FF}"],
            ['hy', 'Armenian',    'Հայերեն',         "\u{1F1E6}\u{1F1F2}"],
            ['ka', 'Georgian',    'ქართული',         "\u{1F1EC}\u{1F1EA}"],
            ['he', 'Hebrew',      'עברית',           "\u{1F1EE}\u{1F1F1}"],
            ['fa', 'Persian',     'فارسی',           "\u{1F1EE}\u{1F1F7}"],
            ['ur', 'Urdu',        'اردو',             "\u{1F1F5}\u{1F1F0}"],
            ['bg', 'Bulgarian',   'Български',       "\u{1F1E7}\u{1F1EC}"],
            ['sr', 'Serbian',     'Српски',          "\u{1F1F7}\u{1F1F8}"],
            ['hr', 'Croatian',    'Hrvatski',        "\u{1F1ED}\u{1F1F7}"],
            ['sk', 'Slovak',      'Slovenčina',      "\u{1F1F8}\u{1F1F0}"],
            ['sl', 'Slovenian',   'Slovenščina',     "\u{1F1F8}\u{1F1EE}"],
            ['et', 'Estonian',    'Eesti',           "\u{1F1EA}\u{1F1EA}"],
            ['lt', 'Lithuanian',  'Lietuvių',        "\u{1F1F1}\u{1F1F9}"],
            ['lv', 'Latvian',     'Latviešu',        "\u{1F1F1}\u{1F1FB}"],
        ];

        return array_map(fn ($r) => [
            'code' => $r[0],
            'label' => $r[1],
            'native' => $r[2],
            'flag' => $r[3],
        ], $rows);
    }

    /**
     * @return bool True if translation chunks are still in-flight after dispatch
     *              (the page should subscribe to SSE for live progress).
     */
    private function ensureTranslations(Restaurant $restaurant, object $menu, string $lang): bool
    {
        $itemIds = $menu->sections->flatMap->items->pluck('id');

        if ($itemIds->isEmpty()) {
            return false;
        }

        // For mixed-source menus, initial (OCR) translations may be tagged
        // with the wrong locale (e.g. English descriptions stored as vi-initial),
        // so we require non-initial entries from the translation pipeline.
        $translationQuery = Translation::where('locale', $lang)
            ->where('translatable_type', MenuItem::class)
            ->whereIn('translatable_id', $itemIds);

        if (($menu->source_locale ?? null) === 'mixed') {
            $translationQuery->where('is_initial', false);
        }

        if ($translationQuery->exists()) {
            return false;
        }

        // Throttle: max 1 translation per menu+locale per hour
        $cacheKey = "menu_translation:{$menu->id}:{$lang}";

        if (Cache::has($cacheKey)) {
            // Already dispatched recently — assume chunks are still in-flight
            // (or finished but data not loaded for this request). Either way,
            // SSE subscription resolves it on the client.
            return true;
        }

        TranslateMenuJob::dispatchSync($menu, $lang);

        // The orchestrator's handle() now fires Bus::batch and returns; chunks
        // crunch in Horizon. Translations land asynchronously, so for this
        // request we treat this as "pending" and let the client subscribe.
        $savedQuery = Translation::where('locale', $lang)
            ->where('translatable_type', MenuItem::class)
            ->whereIn('translatable_id', $itemIds);

        if (($menu->source_locale ?? null) === 'mixed') {
            $savedQuery->where('is_initial', false);
        }

        $translationsSaved = $savedQuery->exists();

        Cache::put($cacheKey, true, now()->addHour());

        return ! $translationsSaved;
    }

    /**
     * @return array<int, array{code: string, label: string, flag: string}>
     */
    private function getAvailableLanguages(Restaurant $restaurant, ?object $menu, ?string $requestedLang = null): array
    {
        $primaryLang = $restaurant->primary_language ?? 'en';
        $langs = [];

        // Always include the source locale (initial translation)
        if ($menu && $menu->source_locale && $menu->source_locale !== 'mixed') {
            $langs[] = [
                'code' => $menu->source_locale,
                'label' => $this->getLanguageLabel($menu->source_locale),
                'flag' => $this->getLanguageFlag($menu->source_locale),
            ];
        }

        // Include primary language if different
        if (! collect($langs)->pluck('code')->contains($primaryLang)) {
            array_unshift($langs, [
                'code' => $primaryLang,
                'label' => $this->getLanguageLabel($primaryLang),
                'flag' => $this->getLanguageFlag($primaryLang),
            ]);
        }

        // Always offer English if not already listed
        if (! collect($langs)->pluck('code')->contains('en')) {
            $langs[] = ['code' => 'en', 'label' => 'English', 'flag' => "\u{1F1EC}\u{1F1E7}"];
        }

        // Include requested language if non-initial translations were generated for it
        // (initial translations may be wrong-language for source_locale='mixed' menus).
        if ($requestedLang && ! collect($langs)->pluck('code')->contains($requestedLang)) {
            $hasTranslations = $menu && Translation::where('locale', $requestedLang)
                ->where('translatable_type', MenuItem::class)
                ->where('is_initial', false)
                ->whereIn('translatable_id', $menu->sections->flatMap->items->pluck('id'))
                ->exists();

            if ($hasTranslations) {
                $langs[] = [
                    'code' => $requestedLang,
                    'label' => $this->getLanguageLabel($requestedLang),
                    'flag' => $this->getLanguageFlag($requestedLang),
                ];
            }
        }

        return array_values($langs);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildItemsJson(?object $menu, string $lang): array
    {
        if (! $menu) {
            return [];
        }

        $items = [];
        foreach ($menu->sections as $section) {
            foreach ($section->items as $item) {
                $entry = [
                    'id' => $item->id,
                    'sectionId' => $section->id,
                    'name' => $item->translate('name', $lang) ?? $item->translate('name', $menu->source_locale ?? 'und') ?? '',
                    'description' => $item->translate('description', $lang) ?? $item->translate('description', $menu->source_locale ?? 'und'),
                    'price' => (float) $item->price_value,
                    'image_url' => $item->image_url,
                    'thumb_url' => $item->thumb_url,
                ];

                $allGroups = $item->optionGroups;
                $sourceLocale = $menu->source_locale ?? 'und';

                $variationGroups = $allGroups->where('is_variation', true);
                if ($variationGroups->isNotEmpty()) {
                    $variants = [];
                    foreach ($variationGroups as $group) {
                        foreach ($group->options as $opt) {
                            $variants[] = [
                                'name' => $opt->translate('name', $lang) ?? $opt->translate('name', $sourceLocale) ?? '',
                                'price' => (float) $item->price_value + (float) $opt->price_adjust,
                            ];
                        }
                    }
                    $entry['variants'] = $variants;
                }

                $optionGroups = $allGroups->where('is_variation', false);
                if ($optionGroups->isNotEmpty()) {
                    $options = [];
                    foreach ($optionGroups as $group) {
                        $options[] = [
                            'id' => $group->id,
                            'name' => $group->translate('name', $lang) ?? $group->translate('name', $sourceLocale) ?? '',
                            'required' => $group->min_select > 0,
                            'type' => $group->max_select === 1 ? 'single' : 'multiple',
                            'max' => $group->max_select,
                            'choices' => $group->options->map(fn ($opt) => [
                                'id' => $opt->id,
                                'name' => $opt->translate('name', $lang) ?? $opt->translate('name', $sourceLocale) ?? '',
                                'price' => (float) $opt->price_adjust,
                            ])->all(),
                        ];
                    }
                    $entry['options'] = $options;
                }

                $items[] = $entry;
            }
        }

        return $items;
    }

    private function getCurrencySymbol(string $currency): string
    {
        return match (strtoupper($currency)) {
            'VND' => "\u{20AB}",
            'USD' => '$',
            'EUR' => "\u{20AC}",
            'RUB' => "\u{20BD}",
            'KZT' => "\u{20B8}",
            default => $currency,
        };
    }

    private function getLanguageLabel(string $code): string
    {
        $iso = new ISO639;
        $native = $iso->nativeByCode1($code, true);

        return $native !== '' ? $native : strtoupper($code);
    }

    /**
     * @return array<string, string>
     */
    private function getUiStrings(string $lang): array
    {
        $strings = [
            'vi' => [
                'search' => 'Tìm món...', 'all' => 'Tất cả', 'powered' => 'Powered by QR Menu',
                'addToCart' => 'Thêm vào', 'cart' => 'Giỏ hàng', 'total' => 'Tổng', 'showWaiter' => 'Đặt hàng',
                'clearCart' => 'Xoá hết', 'close' => 'Đóng', 'back' => 'Quay lại', 'yourOrder' => 'Đơn hàng của bạn',
                'scanOrder' => 'Đưa mã QR cho nhân viên', 'chooseVariant' => 'Chọn loại', 'orderEmpty' => 'Giỏ hàng trống',
                'deleteItem' => 'Xoá', 'required' => 'Bắt buộc', 'optional' => 'Tuỳ chọn',
                'maxChoices' => 'Tối đa {n}', 'updateCart' => 'Cập nhật',
                'added' => 'Đã thêm', 'noResults' => 'Không tìm thấy món',
                'address' => 'Địa chỉ', 'hoursToday' => 'Hôm nay', 'currency' => 'Tiền tệ',
                'openNow' => 'Đang mở', 'closedNow' => 'Đã đóng',
                'closedToday' => 'Đóng cửa hôm nay', 'open24h' => '24 giờ',
                'submitOrder' => 'Gửi đơn hàng', 'placingOrder' => 'Đang gửi đơn',
                'orderPlaced' => 'Đã đặt đơn', 'orderNumber' => 'Đơn',
                'orderFailed' => 'Đặt đơn thất bại', 'orderRequiresTable' => 'Vui lòng quét mã QR trên bàn để đặt đơn',
            ],
            'en' => [
                'search' => 'Search menu...', 'all' => 'All', 'powered' => 'Powered by QR Menu',
                'addToCart' => 'Add to cart', 'cart' => 'Cart', 'total' => 'Total', 'showWaiter' => 'Place order',
                'clearCart' => 'Clear', 'close' => 'Close', 'back' => 'Back', 'yourOrder' => 'Your order',
                'scanOrder' => 'Show QR code to staff', 'chooseVariant' => 'Choose variant', 'orderEmpty' => 'Cart is empty',
                'deleteItem' => 'Delete', 'required' => 'Required', 'optional' => 'Optional',
                'maxChoices' => 'Max {n}', 'updateCart' => 'Update',
                'added' => 'Added', 'noResults' => 'No results found',
                'address' => 'Address', 'hoursToday' => 'Today', 'currency' => 'Currency',
                'openNow' => 'Open now', 'closedNow' => 'Closed',
                'closedToday' => 'Closed today', 'open24h' => '24 hours',
                'submitOrder' => 'Submit order', 'placingOrder' => 'Sending order',
                'orderPlaced' => 'Order placed', 'orderNumber' => 'Order',
                'orderFailed' => 'Order failed', 'orderRequiresTable' => 'Scan the table QR code to order',
            ],
            'ru' => [
                'search' => 'Поиск по меню...', 'all' => 'Все', 'powered' => 'Powered by QR Menu',
                'addToCart' => 'В корзину', 'cart' => 'Корзина', 'total' => 'Итого', 'showWaiter' => 'Заказать',
                'clearCart' => 'Очистить', 'close' => 'Закрыть', 'back' => 'Назад', 'yourOrder' => 'Ваш заказ',
                'scanOrder' => 'Покажите QR-код сотруднику', 'chooseVariant' => 'Выберите вариант', 'orderEmpty' => 'Корзина пуста',
                'deleteItem' => 'Удалить', 'required' => 'Обязательно', 'optional' => 'По желанию',
                'maxChoices' => 'Макс. {n}', 'updateCart' => 'Обновить',
                'added' => 'Добавлено', 'noResults' => 'Ничего не найдено',
                'address' => 'Адрес', 'hoursToday' => 'Сегодня', 'currency' => 'Валюта',
                'openNow' => 'Открыто', 'closedNow' => 'Закрыто',
                'closedToday' => 'Сегодня закрыто', 'open24h' => '24 часа',
                'submitOrder' => 'Отправить заказ', 'placingOrder' => 'Отправляем заказ',
                'orderPlaced' => 'Заказ принят', 'orderNumber' => 'Заказ',
                'orderFailed' => 'Не удалось отправить заказ', 'orderRequiresTable' => 'Отсканируйте QR на столе, чтобы заказать',
            ],
            'kk' => [
                'search' => 'Мәзірден іздеу...', 'all' => 'Барлығы', 'powered' => 'Powered by QR Menu',
                'addToCart' => 'Себетке', 'cart' => 'Себет', 'total' => 'Барлығы', 'showWaiter' => 'Тапсырыс беру',
                'clearCart' => 'Тазалау', 'close' => 'Жабу', 'back' => 'Артқа', 'yourOrder' => 'Сіздің тапсырысыңыз',
                'scanOrder' => 'QR кодты қызметкерге көрсетіңіз', 'chooseVariant' => 'Нұсқаны таңдаңыз', 'orderEmpty' => 'Себет бос',
                'deleteItem' => 'Жою', 'required' => 'Міндетті', 'optional' => 'Қалауы бойынша',
                'maxChoices' => 'Макс. {n}', 'updateCart' => 'Жаңарту',
                'added' => 'Қосылды', 'noResults' => 'Ештеңе табылмады',
                'address' => 'Мекенжай', 'hoursToday' => 'Бүгін', 'currency' => 'Валюта',
                'openNow' => 'Ашық', 'closedNow' => 'Жабық',
                'closedToday' => 'Бүгін жабық', 'open24h' => '24 сағат',
                'submitOrder' => 'Тапсырысты жіберу', 'placingOrder' => 'Жіберілуде',
                'orderPlaced' => 'Тапсырыс қабылданды', 'orderNumber' => 'Тапсырыс',
                'orderFailed' => 'Жіберу сәтсіз', 'orderRequiresTable' => 'Тапсырыс беру үшін үстелдегі QR кодты сканерлеңіз',
            ],
        ];

        return $strings[$lang] ?? $strings['en'];
    }

    private function getLanguageFlag(string $code): string
    {
        return match ($code) {
            'vi' => "\u{1F1FB}\u{1F1F3}",
            'en' => "\u{1F1EC}\u{1F1E7}",
            'ru' => "\u{1F1F7}\u{1F1FA}",
            'kk' => "\u{1F1F0}\u{1F1FF}",
            'zh' => "\u{1F1E8}\u{1F1F3}",
            'ja' => "\u{1F1EF}\u{1F1F5}",
            'ko' => "\u{1F1F0}\u{1F1F7}",
            'th' => "\u{1F1F9}\u{1F1ED}",
            'id' => "\u{1F1EE}\u{1F1E9}",
            default => "\u{1F3F3}\u{FE0F}",
        };
    }
}
