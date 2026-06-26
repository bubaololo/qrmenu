<?php

namespace App\Actions;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Model;
use Silber\PageCache\Cache;

/**
 * Drops the static page-cache files for a restaurant's public menu.
 *
 * The cache stores one file per URL: `/{id}` -> `{id}.html`,
 * `/{id}/{lang}` -> `{id}/{lang}.html`,
 * `/{uniqid}/t/{table}/{lang}` -> `{uniqid}/t/{table}/{lang}.html`.
 * A public menu is reachable by both the numeric id and the uniqid, so a
 * restaurant owns two URL roots; forgetting it deletes both base files and
 * recursively clears both subtrees (every language + table variant).
 *
 * No-op unless page caching is enabled (see config/pagecache.php).
 */
class ForgetMenuPageCache
{
    /**
     * When true, invalidation is suppressed. Used to silence the per-row
     * Translation writes of a bulk LLM batch (TranslateChunkJob), which is
     * invalidated once at batch completion instead.
     */
    private static bool $suppressed = false;

    public function __construct(private Cache $cache) {}

    /**
     * Run a callback with page-cache invalidation suppressed.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutInvalidation(callable $callback): mixed
    {
        $previous = self::$suppressed;
        self::$suppressed = true;

        try {
            return $callback();
        } finally {
            self::$suppressed = $previous;
        }
    }

    /**
     * Invalidate the menu pages of whichever restaurant owns the given model.
     */
    public function forModel(Model $model): void
    {
        if (self::$suppressed || ! config('pagecache.enabled')) {
            return;
        }

        $restaurant = $this->resolveRestaurant($model);

        if ($restaurant !== null) {
            $this->forRestaurant($restaurant);
        }
    }

    public function forRestaurant(Restaurant $restaurant): void
    {
        $this->forKeys([(string) $restaurant->getKey(), (string) $restaurant->uniqid]);
    }

    /**
     * Forget the base file and recursively clear the subtree for each URL root.
     * Accepts plain string keys so a queued batch callback can pass captured
     * scalars (numeric id + uniqid) without serializing a model.
     *
     * @param  array<int, string|null>  $roots
     */
    public function forKeys(array $roots): void
    {
        if (self::$suppressed || ! config('pagecache.enabled')) {
            return;
        }

        foreach (array_unique(array_filter(array_map('strval', $roots))) as $root) {
            $this->cache->forget($root); // {root}.html  (no-lang base page)
            $this->cache->clear($root);  // {root}/ subtree (every language + table)
        }
    }

    private function resolveRestaurant(Model $model): ?Restaurant
    {
        if ($model instanceof Restaurant) {
            return $model;
        }

        $menu = match (true) {
            $model instanceof Menu => $model,
            $model instanceof MenuItem => $model->loadMissing('section.menu')->section?->menu,
            $model instanceof MenuSection => $model->loadMissing('menu')->menu,
            $model instanceof ModifierGroup => $model->loadMissing('menu')->menu,
            $model instanceof ModifierOption => $model->loadMissing('group.menu')->group?->menu,
            default => null,
        };

        return $menu instanceof Menu ? $menu->loadMissing('restaurant')->restaurant : null;
    }
}
