<?php

namespace Database\Seeders;

use App\Models\PromptType;
use Illuminate\Database\Seeder;

class PromptTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PromptType::query()->firstOrCreate(
            ['slug' => 'menu_analyzer'],
            ['name' => 'Menu analyzer'],
        );
    }
}
