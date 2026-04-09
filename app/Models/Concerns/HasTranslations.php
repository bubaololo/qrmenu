<?php

namespace App\Models\Concerns;

use App\Models\Translation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasTranslations
{
    public static function bootHasTranslations(): void
    {
        static::deleting(fn ($model) => $model->translations()->delete());
    }

    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /**
     * Only initial (source/user-entered) translations — for API responses.
     */
    public function initialTranslations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable')->where('is_initial', true);
    }

    /**
     * Get the initial (source) text for a field. No locale lookup — just the original.
     * Works with eager-loaded `initialTranslations` to avoid N+1.
     */
    public function initialText(string $field): ?string
    {
        if ($this->relationLoaded('initialTranslations')) {
            return $this->initialTranslations->first(fn (Translation $t) => $t->field === $field)?->value;
        }

        return $this->translations()
            ->where('field', $field)
            ->where('is_initial', true)
            ->value('value');
    }

    /**
     * Get the translated value for a field in the given locale.
     * Falls back to the initial (source) translation if the requested locale has none.
     * When translations are eager-loaded, no additional DB queries are made.
     */
    public function translate(string $field, string $locale): ?string
    {
        /** @var Collection<int, Translation> $loaded */
        $loaded = $this->relationLoaded('translations') ? $this->translations : collect();

        if ($loaded->isNotEmpty()) {
            $match = $loaded->first(fn (Translation $t) => $t->locale === $locale && $t->field === $field);
            if ($match) {
                return $match->value;
            }

            // Fallback: return any initial translation for this field
            return $loaded->first(fn (Translation $t) => $t->field === $field && $t->is_initial)?->value;
        }

        // Not eager-loaded: query the DB directly
        $match = $this->translations()
            ->where('locale', $locale)
            ->where('field', $field)
            ->value('value');

        if ($match !== null) {
            return $match;
        }

        // Fallback to any initial
        return $this->translations()
            ->where('field', $field)
            ->where('is_initial', true)
            ->value('value');
    }

    /**
     * Persist a translation for the given field + locale.
     */
    public function setTranslation(string $field, string $locale, string $value, bool $isInitial = false): void
    {
        // Only one initial allowed per (type, id, field) — remove old if replacing
        if ($isInitial) {
            $this->translations()
                ->where('field', $field)
                ->where('is_initial', true)
                ->where('locale', '!=', $locale)
                ->delete();
        }

        $this->translations()->updateOrCreate(
            ['locale' => $locale, 'field' => $field],
            ['value' => $value, 'is_initial' => $isInitial],
        );
    }

    /**
     * Return all translations keyed as ['field' => ['locale_code' => 'value']].
     *
     * @return array<string, array<string, string>>
     */
    public function allTranslations(): array
    {
        $result = [];
        foreach ($this->translations()->get() as $t) {
            $result[$t->field][$t->locale] = $t->value;
        }

        return $result;
    }
}
