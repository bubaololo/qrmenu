<?php

namespace App\Http\Controllers\Menus;

use App\Http\Controllers\Controller;
use App\Jobs\TranslateMenuJob;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Matriphe\ISO639\ISO639;

class MenuTranslationController extends Controller
{
    /**
     * List all locales that have translations for a menu.
     */
    public function locales(Menu $menu): JsonResponse
    {
        Gate::authorize('view', $menu);

        $menu->loadMissing('restaurant');

        $sourceLocale = $menu->source_locale;
        $primaryLang = $menu->restaurant?->primary_language ?? 'en';

        return response()->json([
            'data' => $menu->availableLocales(),
            'meta' => [
                'source_locale' => $sourceLocale,
                'primary_language' => $primaryLang,
            ],
        ]);
    }

    /**
     * Trigger translation of a menu into a target locale.
     */
    public function store(Menu $menu, string $locale): JsonResponse
    {
        Gate::authorize('update', $menu);

        $iso = new ISO639;
        if ($iso->languageByCode1($locale) === '') {
            return response()->json(['message' => 'Invalid locale code.'], 422);
        }

        if ($locale === $menu->source_locale) {
            return response()->json(['message' => 'Cannot translate into the source locale.'], 422);
        }

        $cacheKey = "menu_translation:{$menu->id}:{$locale}";

        if (! Cache::has($cacheKey)) {
            TranslateMenuJob::dispatch($menu, $locale);
            Cache::put($cacheKey, true, now()->addHour());
        }

        return response()->json(['message' => 'Translation queued.'], 202);
    }
}
