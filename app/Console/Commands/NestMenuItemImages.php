<?php

namespace App\Console\Commands;

use App\Models\MenuItem;
use App\Services\ImageProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-off (idempotent) reorganizer: move menu-item photos that were uploaded
 * flat into the root (menu-items/{uuid}.webp) into the per-menu subfolder
 * menu-items/{menu_id}/{uuid}.webp, matching the crop/API pipelines, and update
 * each item's image path. Safe to re-run — it only touches flat paths.
 */
class NestMenuItemImages extends Command
{
    protected $signature = 'menu-items:nest-images {--dry-run : Report what would change without moving files}';

    protected $description = 'Move flat menu-item images into menu-items/{menu_id}/ subfolders';

    public function handle(ImageProcessor $processor): int
    {
        $disk = config('image.disk');
        $base = config('image.paths.menu_items');
        $dry = (bool) $this->option('dry-run');

        // Flat = under the base dir but NOT already in a subfolder.
        // Eager-load section.menu: MenuItem's saved hook reads section->menu.
        $items = MenuItem::with('section.menu')
            ->where('image', 'like', $base.'/%')
            ->where('image', 'not like', $base.'/%/%')
            ->get();

        $moved = 0;
        $skipped = 0;
        foreach ($items as $item) {
            $menuId = $item->section?->menu_id;
            if (! $menuId) {
                $this->warn("item {$item->id}: no menu_id (orphan section) — skipped");
                $skipped++;

                continue;
            }

            $old = $item->image;
            $new = $base.'/'.$menuId.'/'.basename($old);
            $oldThumb = $processor->thumbPath($old);
            $newThumb = $base.'/'.$menuId.'/'.basename($oldThumb);

            $this->line(($dry ? '[dry] ' : '')."item {$item->id}: {$old} → {$new}");

            if ($dry) {
                continue;
            }

            $this->moveIfPresent($disk, $old, $new);
            $this->moveIfPresent($disk, $oldThumb, $newThumb);
            $item->update(['image' => $new]);
            $moved++;
        }

        $this->info(($dry ? '[dry] ' : '')."Done. moved={$moved} skipped={$skipped} candidates=".$items->count());

        return self::SUCCESS;
    }

    private function moveIfPresent(string $disk, string $from, string $to): void
    {
        if (Storage::disk($disk)->exists($from) && ! Storage::disk($disk)->exists($to)) {
            Storage::disk($disk)->move($from, $to);
        }
    }
}
