<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Cache;

/**
 * Inlines Vite-built CSS/JS bundles into the rendered HTML.
 *
 * Cold-cache page loads on weak 3G hate extra HTTP requests — each one
 * costs DNS+TCP+TLS roundtrips. Inlining the critical assets collapses
 * 3 requests (HTML + CSS + JS) into 1.
 *
 * Bundle contents are cached in Redis with `filemtime` baked into the key
 * so `npm run build` automatically invalidates without `cache:clear`.
 *
 * In dev (HMR active) all methods return empty — callers fall back to
 * the standard `@vite()` directive to preserve hot-reload.
 */
final class InlineAssets
{
    /**
     * Inline raw CSS bundle by Vite source entry path.
     * Returns '' in dev (HMR) or when the bundle is missing.
     */
    public static function viteCss(string $entry): string
    {
        return self::viteAsset($entry, isJs: false);
    }

    /**
     * Inline raw JS bundle by Vite source entry path. Any literal
     * `</script>` in the bundle is escaped to keep the inline tag intact.
     */
    public static function viteJs(string $entry): string
    {
        return self::viteAsset($entry, isJs: true);
    }

    public static function isHot(): bool
    {
        return app(Vite::class)->isRunningHot();
    }

    private static function viteAsset(string $entry, bool $isJs): string
    {
        if (self::isHot()) {
            return '';
        }

        $manifest = self::manifest();
        $relative = $manifest[$entry]['file'] ?? null;
        if (! is_string($relative)) {
            return '';
        }

        $content = self::cachedFile(public_path('build/'.$relative));
        if ($isJs) {
            $content = str_replace('</script>', '<\\/script>', $content);
        }

        return $content;
    }

    /** @return array<string, array<string, mixed>> */
    private static function manifest(): array
    {
        $manifestPath = public_path('build/manifest.json');
        if (! is_file($manifestPath)) {
            return [];
        }

        return Cache::rememberForever(
            'vite:manifest:'.filemtime($manifestPath),
            static fn (): array => json_decode((string) file_get_contents($manifestPath), true) ?? [],
        );
    }

    private static function cachedFile(string $absolutePath): string
    {
        if (! is_file($absolutePath)) {
            return '';
        }

        return Cache::rememberForever(
            'inline_asset:'.$absolutePath.':'.filemtime($absolutePath),
            static fn (): string => (string) file_get_contents($absolutePath),
        );
    }
}
