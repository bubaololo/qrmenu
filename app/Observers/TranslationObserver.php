<?php

namespace App\Observers;

use App\Jobs\TranslateEntityJob;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Translation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reactively syncs translations across all target locales when an initial
 * (source) translation is created or its value changes.
 *
 * Triggered by Translation::saved (covers both INSERT and UPDATE paths used
 * by HasTranslations::setTranslation -> updateOrCreate). Skips non-initial
 * translations to avoid feedback loops with TranslateChunkJob /
 * TranslateEntityJob writes.
 */
class TranslationObserver
{
    public function saved(Translation $translation): void
    {
        if (! config('llm.translation.auto_sync', true)) {
            return;
        }

        if (! $translation->is_initial) {
            return;
        }

        if (! $translation->wasRecentlyCreated && ! $translation->wasChanged('value')) {
            return;
        }

        if (config('llm.translation.warn_on_bulk_observer', false) && DB::transactionLevel() > 0) {
            Log::channel('llm')->warning('TranslationObserver fired inside DB transaction — bulk path likely forgot Translation::withoutEvents()', [
                'translatable_type' => $translation->translatable_type,
                'translatable_id' => $translation->translatable_id,
                'field_id' => $translation->field_id,
                'locale' => $translation->locale,
            ]);
        }

        $owner = $translation->translatable;
        if (! $owner instanceof Model) {
            return;
        }

        $targetLocales = $this->resolveTargetLocales($owner, $translation->locale);
        if (empty($targetLocales)) {
            return;
        }

        TranslateEntityJob::dispatch(
            $owner,
            $targetLocales,
            $translation->translationField->name,
        );
    }

    /** @return list<string> */
    private function resolveTargetLocales(Model $owner, string $sourceLocale): array
    {
        // Models reach this observer right after a save, often without their
        // parent chain eager-loaded. preventLazyLoading is on in non-prod
        // envs, so use loadMissing to walk to the menu safely.
        $menu = match (true) {
            $owner instanceof MenuItem => $owner->loadMissing('section.menu')->section?->menu,
            $owner instanceof MenuSection => $owner->loadMissing('menu')->menu,
            $owner instanceof ModifierGroup => $owner->loadMissing('menu')->menu,
            $owner instanceof ModifierOption => $owner->loadMissing('group.menu')->group?->menu,
            default => null,
        };

        if (! $menu instanceof Menu) {
            return [];
        }

        // Translate into every available locale except the source one (the
        // locale just written, which equals the menu's source_locale).
        return $menu->availableLocales()
            ->reject(fn (array $locale) => $locale['code'] === $sourceLocale)
            ->pluck('code')
            ->values()
            ->all();
    }
}
