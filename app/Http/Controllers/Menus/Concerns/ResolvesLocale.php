<?php

namespace App\Http\Controllers\Menus\Concerns;

use App\Models\Menu;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ResolvesLocale
{
    /**
     * Reject the request if the Accept-Language header carries a locale that
     * is not in the menu's availableLocales. New languages must be added through
     * POST /menus/{id}/translations/{locale} which runs the LLM translation job.
     *
     * Silently allows requests without a header.
     */
    private function assertLocaleAllowed(Menu $menu): void
    {
        $header = request()->attributes->get('locale_from_header');
        if ($header === null) {
            return;
        }

        $allowed = $menu->availableLocales()->pluck('code')->all();
        if (! in_array($header, $allowed, true)) {
            throw new HttpException(
                422,
                "Locale '{$header}' is not available for this menu. Add it first via POST /menus/{$menu->id}/translations/{$header}.",
            );
        }
    }

    /**
     * Resolve the locale for editing a translation on the given menu.
     *
     * Returns [$locale, $isInitial] where $isInitial is true when the resolved
     * locale matches the menu's initial (source-of-truth) locale. For mixed-
     * language menus that initial locale is the restaurant's primary_language,
     * NOT the 'mixed' sentinel — see {@see Menu::initialLocale()}. Comparing
     * against the raw source_locale here would leave every new entity on a
     * mixed menu with is_initial=false and no source value forever.
     *
     * Also validates Accept-Language against availableLocales (see
     * {@see self::assertLocaleAllowed()}).
     *
     * @return array{string, bool}
     */
    private function resolveLocale(Menu $menu): array
    {
        $this->assertLocaleAllowed($menu);

        $initialLocale = $menu->initialLocale();
        $locale = request()->attributes->get('locale_from_header') ?? $initialLocale;

        if ($locale === null) {
            throw new HttpException(
                422,
                'Menu has no initial locale (no source_locale or primary_language) and no Accept-Language header was provided.',
            );
        }

        return [$locale, $locale === $initialLocale];
    }
}
