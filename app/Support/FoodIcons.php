<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Icon;
use Illuminate\Support\Facades\Cache;

/**
 * Returns the inline <symbol>-set sprite for menu icons.
 *
 * Source of truth: the `icons` table (populated by `php artisan icons:sync`
 * from resources/img/menu/*.svg). Symbols are cached per-name in Redis and
 * a "full" key holds the entire sprite for the public route /menu-sprite.svg.
 * Both layers are invalidated by App\Observers\IconObserver on icon mutation.
 */
final class FoodIcons
{
    /**
     * @param  list<string>|null  $names  When null, returns the full sprite (every icon in DB).
     *                                    When an array, returns only the symbols for those names
     *                                    (unknown names are silently dropped).
     */
    public static function sprite(?array $names = null): string
    {
        if ($names === null) {
            return Cache::rememberForever('icon_sprite:full', static function (): string {
                $body = Icon::query()
                    ->whereNotNull('svg')
                    ->where('svg', '!=', '')
                    ->orderBy('name')
                    ->pluck('svg')
                    ->implode('');

                return $body !== '' ? self::wrap($body) : '';
            });
        }

        $symbols = [];
        foreach (array_unique(array_filter($names, fn ($n) => is_string($n) && $n !== '')) as $name) {
            $symbol = Cache::rememberForever(
                "icon_sprite:symbol:{$name}",
                static fn (): ?string => Icon::where('name', $name)->value('svg') ?: null,
            );
            if ($symbol) {
                $symbols[] = $symbol;
            }
        }

        return $symbols !== [] ? self::wrap(implode('', $symbols)) : '';
    }

    /**
     * Cached comma-separated list of all icon names — fed into the LLM
     * analyzer prompt as a placeholder substitution.
     */
    public static function namesList(): string
    {
        return Cache::rememberForever(
            'icon_names:list',
            static fn (): string => Icon::query()->orderBy('name')->pluck('name')->implode(', '),
        );
    }

    private static function wrap(string $body): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" '
            .'style="position:absolute;width:0;height:0;overflow:hidden" '
            .'aria-hidden="true"><defs>'.$body.'</defs></svg>';
    }
}
