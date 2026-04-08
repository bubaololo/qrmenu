<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use Illuminate\View\View;

class MenuPageController extends Controller
{
    public function show(Restaurant $restaurant, ?string $lang = null): View
    {
        $restaurant->load([
            'translations',
            'activeMenu.sections.translations',
            'activeMenu.sections.items.translations',
            'activeMenu.sections.items.variations.translations',
            'activeMenu.sections.items.variations.options.translations',
            'activeMenu.sections.items.optionGroups.translations',
            'activeMenu.sections.items.optionGroups.options.translations',
        ]);

        $menu = $restaurant->activeMenu;
        $primaryLang = $restaurant->primary_language ?? 'en';

        // Normalize: 'mixed' source locale means no single lang → default to primaryLang
        if ($lang === null || $lang === 'mixed') {
            $lang = $primaryLang;
        }

        $languages = $this->getAvailableLanguages($restaurant, $menu);

        if (! collect($languages)->pluck('code')->contains($lang)) {
            $lang = $primaryLang;
        }

        $itemsJson = $this->buildItemsJson($menu, $lang);
        $currencySymbol = $this->getCurrencySymbol($restaurant->currency ?? 'USD');
        $uiStrings = $this->getUiStrings($lang);

        return view('menu', compact(
            'restaurant',
            'menu',
            'lang',
            'languages',
            'itemsJson',
            'currencySymbol',
            'primaryLang',
            'uiStrings',
        ));
    }

    /**
     * @return array<int, array{code: string, label: string, flag: string}>
     */
    private function getAvailableLanguages(Restaurant $restaurant, ?object $menu): array
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
                ];

                if ($item->variations->isNotEmpty()) {
                    $variants = [];
                    foreach ($item->variations as $variation) {
                        foreach ($variation->options as $opt) {
                            $variants[] = [
                                'name' => $opt->translate('name', $lang) ?? $opt->translate('name', $menu->source_locale ?? 'und') ?? '',
                                'price' => (float) $item->price_value + (float) $opt->price_adjust,
                            ];
                        }
                    }
                    $entry['variants'] = $variants;
                }

                if ($item->optionGroups->isNotEmpty()) {
                    $options = [];
                    foreach ($item->optionGroups as $group) {
                        $options[] = [
                            'id' => $group->id,
                            'name' => $group->translate('name', $lang) ?? $group->translate('name', $menu->source_locale ?? 'und') ?? '',
                            'required' => $group->min_select > 0,
                            'type' => $group->max_select === 1 ? 'single' : 'multiple',
                            'max' => $group->max_select,
                            'choices' => $group->options->map(fn ($opt) => [
                                'id' => $opt->id,
                                'name' => $opt->translate('name', $lang) ?? $opt->translate('name', $menu->source_locale ?? 'und') ?? '',
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
        return match ($code) {
            'vi' => "Ti\u{1EBF}ng Vi\u{1EC7}t",
            'en' => 'English',
            'ru' => "\u{0420}\u{0443}\u{0441}\u{0441}\u{043A}\u{0438}\u{0439}",
            'kk' => "\u{049A}\u{0430}\u{0437}\u{0430}\u{049B}\u{0448}\u{0430}",
            'zh' => "\u{4E2D}\u{6587}",
            'ja' => "\u{65E5}\u{672C}\u{8A9E}",
            'ko' => "\u{D55C}\u{AD6D}\u{C5B4}",
            'th' => "\u{0E44}\u{0E17}\u{0E22}",
            'id' => 'Indonesia',
            default => strtoupper($code),
        };
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
            ],
            'en' => [
                'search' => 'Search menu...', 'all' => 'All', 'powered' => 'Powered by QR Menu',
                'addToCart' => 'Add to cart', 'cart' => 'Cart', 'total' => 'Total', 'showWaiter' => 'Place order',
                'clearCart' => 'Clear', 'close' => 'Close', 'back' => 'Back', 'yourOrder' => 'Your order',
                'scanOrder' => 'Show QR code to staff', 'chooseVariant' => 'Choose variant', 'orderEmpty' => 'Cart is empty',
                'deleteItem' => 'Delete', 'required' => 'Required', 'optional' => 'Optional',
                'maxChoices' => 'Max {n}', 'updateCart' => 'Update',
                'added' => 'Added', 'noResults' => 'No results found',
            ],
            'ru' => [
                'search' => 'Поиск по меню...', 'all' => 'Все', 'powered' => 'Powered by QR Menu',
                'addToCart' => 'В корзину', 'cart' => 'Корзина', 'total' => 'Итого', 'showWaiter' => 'Заказать',
                'clearCart' => 'Очистить', 'close' => 'Закрыть', 'back' => 'Назад', 'yourOrder' => 'Ваш заказ',
                'scanOrder' => 'Покажите QR-код сотруднику', 'chooseVariant' => 'Выберите вариант', 'orderEmpty' => 'Корзина пуста',
                'deleteItem' => 'Удалить', 'required' => 'Обязательно', 'optional' => 'По желанию',
                'maxChoices' => 'Макс. {n}', 'updateCart' => 'Обновить',
                'added' => 'Добавлено', 'noResults' => 'Ничего не найдено',
            ],
            'kk' => [
                'search' => 'Мәзірден іздеу...', 'all' => 'Барлығы', 'powered' => 'Powered by QR Menu',
                'addToCart' => 'Себетке', 'cart' => 'Себет', 'total' => 'Барлығы', 'showWaiter' => 'Тапсырыс беру',
                'clearCart' => 'Тазалау', 'close' => 'Жабу', 'back' => 'Артқа', 'yourOrder' => 'Сіздің тапсырысыңыз',
                'scanOrder' => 'QR кодты қызметкерге көрсетіңіз', 'chooseVariant' => 'Нұсқаны таңдаңыз', 'orderEmpty' => 'Себет бос',
                'deleteItem' => 'Жою', 'required' => 'Міндетті', 'optional' => 'Қалауы бойынша',
                'maxChoices' => 'Макс. {n}', 'updateCart' => 'Жаңарту',
                'added' => 'Қосылды', 'noResults' => 'Ештеңе табылмады',
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
