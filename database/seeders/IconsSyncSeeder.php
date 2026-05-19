<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class IconsSyncSeeder extends Seeder
{
    /**
     * Delegate to the `icons:sync` artisan command so the same SVG-to-DB
     * transformation logic runs both for `migrate:fresh --seed` and for
     * manual maintenance after SVG file changes.
     */
    public function run(): void
    {
        Artisan::call('icons:sync', [], $this->command?->getOutput());
    }
}
