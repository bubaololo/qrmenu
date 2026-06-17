<?php

namespace App\Http\Controllers;

use App\Jobs\TranslateMenuJob;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\Translation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

        // Normalize: null or invalid ISO-639-1 code → default to primaryLang
        $iso = new ISO639;
        if ($lang === null || ($lang !== $primaryLang && $iso->languageByCode1($lang) === '')) {
            $lang = $primaryLang;
        }

        // Scope translation eager-loads to (a) the requested locale and (b) the
        // menu's source_locale where MenuItem/Section/etc. initials live. This
        // guarantees translate()'s initial-fallback always finds its source row
        // in the loaded collection. Restaurant and Zone names are plain columns
        // and don't need eager-loading.
        $sourceLocale = $menu?->source_locale ?? $primaryLang;
        $locales = array_values(array_unique(array_filter([$lang, $sourceLocale, $primaryLang])));
        $scope = fn ($q) => $q->whereIn('locale', $locales);

        $activeSection = fn ($q) => $q->where('is_active', true);
        $visibleItem = fn ($q) => $q->where('is_visible', true);

        $restaurant->load([
            'menu.sections' => $activeSection,
            'menu.sections.icon',
            'menu.sections.translations' => $scope,
            'menu.sections.items' => $visibleItem,
            'menu.sections.items.translations' => $scope,
            'menu.sections.items.modifierGroups.translations' => $scope,
            'menu.sections.items.modifierGroups.options.translations' => $scope,
            'menu.sections.items.modifierGroups.options.driverPrices' => fn ($q) => $q,
        ]);

        $menu = $restaurant->menu;

        // On-demand translation: if locale not initial and no translations exist, trigger LLM.
        $effectiveSource = $menu?->source_locale ?? $primaryLang;
        $translationPending = false;
        $translationChunkTotal = 0;
        $requestedLang = $lang;
        if ($menu && $lang !== $effectiveSource) {
            $translationChunkTotal = $this->ensureTranslations($restaurant, $menu, $lang);
            $translationPending = $translationChunkTotal > 0;
            $menu = $restaurant->menu; // refresh after potential translation
        }

        $languages = $this->getAvailableLanguages($restaurant, $menu, $lang);

        if (! collect($languages)->pluck('code')->contains($lang)) {
            $lang = $primaryLang;
        }

        // The page renders fallback strings while translation chunks crunch; the
        // progress banner needs the originally-requested locale so it can subscribe
        // to *that* WebSocket topic and reload the page when chunks land.
        $translationLocale = $translationPending ? $requestedLang : null;

        $currencyCode = strtoupper($restaurant->currency ?? 'USD');
        $currencySymbol = $this->getCurrencySymbol($currencyCode);
        $uiStrings = $this->getUiStrings($lang);
        $heroInfo = $this->buildHeroInfo($restaurant, $uiStrings);

        $locales = $this->buildLocaleList($languages, $this->getCommonLanguages());

        return view('menu', compact(
            'restaurant',
            'menu',
            'lang',
            'currencyCode',
            'currencySymbol',
            'primaryLang',
            'uiStrings',
            'heroInfo',
            'translationPending',
            'translationLocale',
            'translationChunkTotal',
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
     *     phone: ?string,
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
            'phone' => $restaurant->phone,
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
     * Ensure a translation exists (or is running) for the requested locale.
     *
     * @return int Number of translation chunks the page should show progress for
     *             (0 = nothing pending: already translated, or a prior run failed
     *             and the throttle still blocks a retry). >0 means a batch is in
     *             flight — the banner renders that many progress segments and the
     *             WebSocket fills them as chunks land.
     */
    private function ensureTranslations(Restaurant $restaurant, object $menu, string $lang): int
    {
        $itemIds = $menu->sections->flatMap->items->pluck('id');

        if ($itemIds->isEmpty()) {
            return 0;
        }

        $hasTranslations = Translation::where('locale', $lang)
            ->where('translatable_type', MenuItem::class)
            ->whereIn('translatable_id', $itemIds)
            ->exists();

        if ($hasTranslations) {
            return 0;
        }

        $batchName = "menu-translation-{$menu->id}-{$lang}";

        // Already running? Show live progress on THIS view too (not only on the
        // request that started it), so a reload mid-translation still sees the bar
        // instead of a silent English fallback. A failed/finished batch has
        // finished_at set, so it won't match here.
        $running = DB::table('job_batches')
            ->where('name', $batchName)
            ->whereNull('finished_at')
            ->orderByDesc('created_at')
            ->first();

        if ($running) {
            return max(1, (int) $running->total_jobs);
        }

        // Throttle: one dispatch per menu+locale per hour. Key set but no running
        // batch and no translations → the previous run failed; don't re-dispatch
        // and don't show a stuck banner.
        $cacheKey = "menu_translation:{$menu->id}:{$lang}";

        if (Cache::has($cacheKey)) {
            return 0;
        }

        TranslateMenuJob::dispatchSync($menu, $lang);
        Cache::put($cacheKey, true, now()->addHour());

        // dispatchSync created the Bus::batch synchronously; read its chunk count
        // so the banner can render the correct number of segments immediately.
        $total = (int) (DB::table('job_batches')
            ->where('name', $batchName)
            ->orderByDesc('created_at')
            ->value('total_jobs') ?? 0);

        return max(1, $total);
    }

    /**
     * @return array<int, array{code: string, label: string, flag: string}>
     */
    private function getAvailableLanguages(Restaurant $restaurant, ?object $menu, ?string $requestedLang = null): array
    {
        $primaryLang = $restaurant->primary_language ?? 'en';
        $langs = [];

        // Always include the source locale (initial translation)
        if ($menu && $menu->source_locale) {
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

        // Include requested language if non-initial translations were generated for it.
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
                'addons' => 'Thêm', 'maxChoices' => 'Tối đa {n}', 'updateCart' => 'Cập nhật',
                'added' => 'Đã thêm', 'noResults' => 'Không tìm thấy món',
                'address' => 'Địa chỉ', 'hoursToday' => 'Hôm nay', 'currency' => 'Tiền tệ',
                'openNow' => 'Đang mở', 'closedNow' => 'Đã đóng',
                'closedToday' => 'Đóng cửa hôm nay', 'open24h' => '24 giờ',
                'details' => 'Thông tin', 'copy' => 'Sao chép', 'copied' => 'Đã sao chép',
                'submitOrder' => 'Gửi đơn hàng', 'placingOrder' => 'Đang gửi đơn',
                'orderPlaced' => 'Đã đặt đơn', 'orderNumber' => 'Đơn',
                'orderFailed' => 'Đặt đơn thất bại', 'orderRequiresTable' => 'Vui lòng quét mã QR trên bàn để đặt đơn',
                'recommended' => 'Đề xuất',
            ],
            'en' => [
                'search' => 'Search menu...', 'all' => 'All', 'powered' => 'Powered by QR Menu',
                'addToCart' => 'Add to cart', 'cart' => 'Cart', 'total' => 'Total', 'showWaiter' => 'Place order',
                'clearCart' => 'Clear', 'close' => 'Close', 'back' => 'Back', 'yourOrder' => 'Your order',
                'scanOrder' => 'Show QR code to staff', 'chooseVariant' => 'Choose variant', 'orderEmpty' => 'Cart is empty',
                'deleteItem' => 'Delete', 'required' => 'Required', 'optional' => 'Optional',
                'addons' => 'Add-ons', 'maxChoices' => 'Max {n}', 'updateCart' => 'Update',
                'added' => 'Added', 'noResults' => 'No results found',
                'address' => 'Address', 'hoursToday' => 'Today', 'currency' => 'Currency',
                'openNow' => 'Open now', 'closedNow' => 'Closed',
                'closedToday' => 'Closed today', 'open24h' => '24 hours',
                'details' => 'Details', 'copy' => 'Copy', 'copied' => 'Copied',
                'submitOrder' => 'Submit order', 'placingOrder' => 'Sending order',
                'orderPlaced' => 'Order placed', 'orderNumber' => 'Order',
                'orderFailed' => 'Order failed', 'orderRequiresTable' => 'Scan the table QR code to order',
                'recommended' => 'Recommended',
            ],
            'ru' => [
                'search' => 'Поиск по меню...', 'all' => 'Все', 'powered' => 'Powered by QR Menu',
                'addToCart' => 'В корзину', 'cart' => 'Корзина', 'total' => 'Итого', 'showWaiter' => 'Заказать',
                'clearCart' => 'Очистить', 'close' => 'Закрыть', 'back' => 'Назад', 'yourOrder' => 'Ваш заказ',
                'scanOrder' => 'Покажите QR-код сотруднику', 'chooseVariant' => 'Выберите вариант', 'orderEmpty' => 'Корзина пуста',
                'deleteItem' => 'Удалить', 'required' => 'Обязательно', 'optional' => 'По желанию',
                'addons' => 'Добавки', 'maxChoices' => 'Макс. {n}', 'updateCart' => 'Обновить',
                'added' => 'Добавлено', 'noResults' => 'Ничего не найдено',
                'address' => 'Адрес', 'hoursToday' => 'Сегодня', 'currency' => 'Валюта',
                'openNow' => 'Открыто', 'closedNow' => 'Закрыто',
                'closedToday' => 'Сегодня закрыто', 'open24h' => '24 часа',
                'details' => 'Информация', 'copy' => 'Копировать', 'copied' => 'Скопировано',
                'submitOrder' => 'Отправить заказ', 'placingOrder' => 'Отправляем заказ',
                'orderPlaced' => 'Заказ принят', 'orderNumber' => 'Заказ',
                'orderFailed' => 'Не удалось отправить заказ', 'orderRequiresTable' => 'Отсканируйте QR на столе, чтобы заказать',
                'recommended' => 'Рекомендуем',
            ],
            'kk' => [
                'search' => 'Мәзірден іздеу...', 'all' => 'Барлығы', 'powered' => 'Powered by QR Menu',
                'addToCart' => 'Себетке', 'cart' => 'Себет', 'total' => 'Барлығы', 'showWaiter' => 'Тапсырыс беру',
                'clearCart' => 'Тазалау', 'close' => 'Жабу', 'back' => 'Артқа', 'yourOrder' => 'Сіздің тапсырысыңыз',
                'scanOrder' => 'QR кодты қызметкерге көрсетіңіз', 'chooseVariant' => 'Нұсқаны таңдаңыз', 'orderEmpty' => 'Себет бос',
                'deleteItem' => 'Жою', 'required' => 'Міндетті', 'optional' => 'Қалауы бойынша',
                'addons' => 'Қосымшалар', 'maxChoices' => 'Макс. {n}', 'updateCart' => 'Жаңарту',
                'added' => 'Қосылды', 'noResults' => 'Ештеңе табылмады',
                'address' => 'Мекенжай', 'hoursToday' => 'Бүгін', 'currency' => 'Валюта',
                'openNow' => 'Ашық', 'closedNow' => 'Жабық',
                'closedToday' => 'Бүгін жабық', 'open24h' => '24 сағат',
                'details' => 'Ақпарат', 'copy' => 'Көшіру', 'copied' => 'Көшірілді',
                'submitOrder' => 'Тапсырысты жіберу', 'placingOrder' => 'Жіберілуде',
                'orderPlaced' => 'Тапсырыс қабылданды', 'orderNumber' => 'Тапсырыс',
                'orderFailed' => 'Жіберу сәтсіз', 'orderRequiresTable' => 'Тапсырыс беру үшін үстелдегі QR кодты сканерлеңіз',
                'recommended' => 'Ұсынамыз',
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
