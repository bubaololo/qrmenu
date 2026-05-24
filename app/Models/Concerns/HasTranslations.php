<?php

namespace App\Models\Concerns;

use App\Models\Translation;
use App\Models\TranslationField;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasTranslations
{
    public static function bootHasTranslations(): void
    {
        static::deleting(function ($model): void {
            $model->translations()->delete();
        });
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
            return $this->initialTranslations->first(fn (Translation $t) => $t->field_id === static::resolveFieldId($field))?->value;
        }

        return $this->translations()
            ->where('field_id', static::resolveFieldId($field))
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
        $fieldId = static::resolveFieldId($field);

        // Use the eager-loaded collection authoritatively when present — even if
        // empty for this row. Falling through to a DB query when the relation is
        // already loaded would be a redundant N+1.
        if ($this->relationLoaded('translations')) {
            /** @var Collection<int, Translation> $loaded */
            $loaded = $this->translations;
            $match = $loaded->first(fn (Translation $t) => $t->locale === $locale && $t->field_id === $fieldId);
            if ($match) {
                return $match->value;
            }

            return $loaded->first(fn (Translation $t) => $t->field_id === $fieldId && $t->is_initial)?->value;
        }

        $match = $this->translations()
            ->where('locale', $locale)
            ->where('field_id', $fieldId)
            ->value('value');

        if ($match !== null) {
            return $match;
        }

        return $this->translations()
            ->where('field_id', $fieldId)
            ->where('is_initial', true)
            ->value('value');
    }

    /**
     * Return translated text for the given field, respecting the Accept-Language request header.
     * Falls back to the initial (source) translation when no header locale is set or no translation exists.
     */
    public function localizedText(string $field): ?string
    {
        $locale = request()->attributes->get('locale_from_header');

        if ($locale) {
            return $this->translate($field, $locale);
        }

        return $this->initialText($field);
    }

    /**
     * Persist a translation for the given field + locale.
     *
     * Refuses to overwrite an existing initial (source) translation with a
     * non-initial one — source data must never be destroyed by a translation
     * pass. This matters for mixed-language menus where a translation pipeline
     * targets the same locale as the OCR-captured initial.
     */
    public function setTranslation(string $field, string $locale, string $value, bool $isInitial = false): void
    {
        // 'mixed' is an attribute of menus.source_locale (multi-language source),
        // never a locale for a concrete translation row. Reject early so the bug
        // surfaces at the call site instead of corrupting translations data.
        if ($locale === 'mixed') {
            throw new \InvalidArgumentException(
                "Cannot store translation with locale='mixed'. 'mixed' marks a multi-language source menu, not a translation target."
            );
        }

        $fieldId = static::resolveFieldId($field);

        if (! $isInitial) {
            $existing = $this->translations()
                ->where('locale', $locale)
                ->where('field_id', $fieldId)
                ->first();

            if ($existing && $existing->is_initial) {
                return;
            }
        } else {
            // Only one initial allowed per (type, id, field) — remove old if replacing
            $this->translations()
                ->where('field_id', $fieldId)
                ->where('is_initial', true)
                ->where('locale', '!=', $locale)
                ->delete();
        }

        $this->translations()->updateOrCreate(
            ['locale' => $locale, 'field_id' => $fieldId],
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
        foreach ($this->translations()->with('translationField')->get() as $t) {
            $result[$t->translationField->name][$t->locale] = $t->value;
        }

        return $result;
    }

    /** @var array<string, int> */
    private static array $fieldIdCache = [];

    private static function resolveFieldId(string $name): int
    {
        return self::$fieldIdCache[$name] ??= TranslationField::firstOrCreate(['name' => $name])->id;
    }

    /** Reset the per-process field-id cache. Use between tests when DB is rolled back. */
    public static function clearTranslationFieldCache(): void
    {
        self::$fieldIdCache = [];
    }
}
