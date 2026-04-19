<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Resolves menu-icon IDs into inline <symbol> markup read from disk.
 * Only the icons actually used on a page are inlined, avoiding a render-blocking sprite fetch.
 *
 * Source assets: resources/icons/menu/*.svg (stroke-style, 24×24, #141B34 ink).
 * The ID of an icon is its filename without .svg.
 */
final class FoodIcons
{
    private const SOURCE_DIR = 'icons/menu';

    /**
     * Returns true if the given ID maps to a known icon file.
     */
    public static function exists(string $id): bool
    {
        return self::path($id) !== null;
    }

    /**
     * Returns the full inline sprite block for a set of icon IDs.
     * Duplicates and unknown IDs are silently dropped. Output is an empty string if no valid IDs.
     *
     * @param  iterable<string|null>  $ids
     */
    public static function sprite(iterable $ids): string
    {
        $symbols = [];
        foreach ($ids as $id) {
            if (! is_string($id) || $id === '') {
                continue;
            }
            if (isset($symbols[$id])) {
                continue;
            }
            $symbol = self::symbol($id);
            if ($symbol !== null) {
                $symbols[$id] = $symbol;
            }
        }

        if ($symbols === []) {
            return '';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true"><defs>'
            .implode('', $symbols)
            .'</defs></svg>';
    }

    /**
     * Reads + transforms a single SVG into a <symbol> string, memoized.
     */
    public static function symbol(string $id): ?string
    {
        $safeId = self::sanitizeId($id);
        if ($safeId === null) {
            return null;
        }

        $path = self::path($safeId);
        if ($path === null) {
            return null;
        }

        $cacheKey = 'food_icon:symbol:'.$safeId.':'.filemtime($path);

        return Cache::rememberForever($cacheKey, static function () use ($path, $safeId): string {
            $raw = (string) file_get_contents($path);

            // Strip root <svg ...> and trailing </svg>, keep inner geometry.
            $inner = preg_replace('/^\s*<svg[^>]*>/s', '', $raw, 1) ?? '';
            $inner = preg_replace('#</svg>\s*$#', '', $inner, 1) ?? '';

            // Theme the stroke color so icons inherit currentColor.
            $inner = str_replace('#141B34', 'currentColor', (string) $inner);

            return '<symbol id="'.$safeId.'" viewBox="0 0 24 24" fill="none">'.trim($inner).'</symbol>';
        });
    }

    /**
     * Resolves the absolute filesystem path for an icon, or null if no such file exists.
     * Restricted to files inside the source directory — prevents path traversal.
     */
    public static function path(string $id): ?string
    {
        $safeId = self::sanitizeId($id);
        if ($safeId === null) {
            return null;
        }

        $full = resource_path(self::SOURCE_DIR.DIRECTORY_SEPARATOR.$safeId.'.svg');

        if (! is_file($full)) {
            return null;
        }

        return $full;
    }

    /**
     * Ensures the ID can only reference a plain filename in the icon directory.
     */
    private static function sanitizeId(string $id): ?string
    {
        $id = trim($id);

        if ($id === '' || ! Str::match('/^[a-z0-9][a-z0-9_-]*$/i', $id)) {
            return null;
        }

        return $id;
    }
}
