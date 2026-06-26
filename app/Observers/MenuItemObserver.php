<?php

namespace App\Observers;

use App\Actions\ForgetMenuPageCache;
use App\Jobs\DeleteImageFilesJob;
use App\Models\MenuItem;
use App\Services\AnalysisEventBroker;
use App\Services\ImageProcessor;

class MenuItemObserver
{
    /** @var array<int, array<string, array<int, string>>> */
    private static array $pendingPaths = [];

    /**
     * When true, menu-item change events do not broadcast. Used to silence the
     * analysis bulk import (SaveMenuAnalysisAction), which creates many items at
     * once and would otherwise flood the restaurant channel with toasts.
     */
    private static bool $muted = false;

    /**
     * Run a callback with menu-item change broadcasts suppressed.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function muted(callable $callback): mixed
    {
        $previous = self::$muted;
        self::$muted = true;

        try {
            return $callback();
        } finally {
            self::$muted = $previous;
        }
    }

    public function created(MenuItem $item): void
    {
        app(ForgetMenuPageCache::class)->forModel($item);
        $this->broadcastChange($item, 'menu-item.created');
    }

    public function updated(MenuItem $item): void
    {
        app(ForgetMenuPageCache::class)->forModel($item);
        $this->broadcastChange($item, 'menu-item.updated');
    }

    /**
     * Capture file paths before the row is deleted so the post-delete event can dispatch cleanup.
     * Only relevant for direct $item->delete(); when MenuItem rows are wiped via FK CASCADE from
     * a parent (section/menu/restaurant), the parent observer collects the paths instead.
     */
    public function deleting(MenuItem $item): void
    {
        if (! $item->image) {
            return;
        }

        $processor = app(ImageProcessor::class);
        $disk = config('image.disk');

        self::$pendingPaths[$item->id] = [
            $disk => [$item->image, $processor->thumbPath($item->image)],
        ];
    }

    public function deleted(MenuItem $item): void
    {
        $paths = self::$pendingPaths[$item->id] ?? null;
        unset(self::$pendingPaths[$item->id]);

        if ($paths) {
            DeleteImageFilesJob::dispatch($paths);
        }

        app(ForgetMenuPageCache::class)->forModel($item);
        $this->broadcastChange($item, 'menu-item.deleted');
    }

    /**
     * Broadcast a menu-item change to the restaurant admin channel so the SPA can
     * toast + invalidate its menu cache. Best-effort: muted during bulk import and
     * silent when the item's menu can't be resolved.
     */
    private function broadcastChange(MenuItem $item, string $event): void
    {
        if (self::$muted) {
            return;
        }

        $item->loadMissing('section.menu');
        $menu = $item->section?->menu;
        if ($menu === null) {
            return;
        }

        app(AnalysisEventBroker::class)->publish(
            "restaurant.{$menu->restaurant_id}",
            $event,
            [
                'item_id' => $item->id,
                'section_id' => $item->section_id,
                'menu_id' => $menu->id,
                'name' => $item->name,
            ],
        );
    }
}
