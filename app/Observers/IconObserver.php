<?php

namespace App\Observers;

use App\Models\Icon;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates Redis sprite + names caches whenever an Icon row changes.
 * Bulk operations should wrap their writes in Icon::withoutEvents() and
 * flush the same keys once at the end (see App\Console\Commands\IconsSync).
 */
class IconObserver
{
    public function saved(Icon $icon): void
    {
        $this->flush($icon);
    }

    public function deleted(Icon $icon): void
    {
        $this->flush($icon);
    }

    private function flush(Icon $icon): void
    {
        Cache::forget('icon_sprite:full');
        Cache::forget('icon_names:list');
        Cache::forget("icon_sprite:symbol:{$icon->name}");
    }
}
