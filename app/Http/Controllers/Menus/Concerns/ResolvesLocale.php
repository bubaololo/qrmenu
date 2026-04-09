<?php

namespace App\Http\Controllers\Menus\Concerns;

trait ResolvesLocale
{
    /**
     * Resolve the locale for the current request.
     *
     * Returns [$locale, $isInitial] where $isInitial is true when the resolved
     * locale matches the source locale of the content being edited.
     *
     * @return array{string, bool}
     */
    private function resolveLocale(string $sourceLocale): array
    {
        $locale = request()->attributes->get('locale_from_header') ?? $sourceLocale;
        $isInitial = ($locale === $sourceLocale);

        return [$locale, $isInitial];
    }
}
