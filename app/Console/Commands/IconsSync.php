<?php

namespace App\Console\Commands;

use App\Models\Icon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('icons:sync')]
#[Description('Synchronize the icons table from resources/img/menu/*.svg files. Flushes Redis sprite caches at the end.')]
class IconsSync extends Command
{
    public function handle(): int
    {
        $files = glob(resource_path('img/menu/*.svg')) ?: [];

        if ($files === []) {
            $this->warn('No SVG files found in resources/img/menu/');

            return self::SUCCESS;
        }

        $synced = 0;
        $skipped = 0;
        $names = [];

        Icon::withoutEvents(function () use ($files, &$synced, &$skipped, &$names): void {
            foreach ($files as $path) {
                $name = pathinfo($path, PATHINFO_FILENAME);
                $symbol = $this->buildSymbol($path, $name);

                if ($symbol === null) {
                    $skipped++;
                    $this->warn("  ✗ {$name} — could not parse SVG");

                    continue;
                }

                Icon::updateOrCreate(['name' => $name], ['svg' => $symbol]);
                $names[] = $name;
                $synced++;
            }
        });

        Cache::forget('icon_sprite:full');
        Cache::forget('icon_names:list');
        foreach ($names as $name) {
            Cache::forget("icon_sprite:symbol:{$name}");
        }

        $this->info("Synced {$synced} icons. Skipped {$skipped}.");

        return self::SUCCESS;
    }

    /**
     * Read an SVG file and produce a self-contained <symbol> body.
     * Strips outer <svg>, themes ink to currentColor, wraps with <symbol id=...>.
     */
    private function buildSymbol(string $path, string $name): ?string
    {
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $inner = preg_replace('/^\s*<svg[^>]*>/s', '', $raw, 1);
        $inner = preg_replace('#</svg>\s*$#', '', (string) $inner, 1);

        if ($inner === null || trim((string) $inner) === '') {
            return null;
        }

        $inner = str_replace('#141B34', 'currentColor', (string) $inner);

        return '<symbol id="'.$name.'" viewBox="0 0 24 24" fill="none">'.trim($inner).'</symbol>';
    }
}
