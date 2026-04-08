<?php

namespace App\Console\Commands;

use App\Actions\SaveMenuAnalysisAction;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\MenuJson;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('menu:import-json
    {file : Path to JSON file (LLM response format)}
    {--restaurant= : Restaurant ID to attach the menu to (creates a temp one if omitted)}
    {--activate : Mark the created menu as active}
    {--dry-run : Parse and report only — do not write to DB}
')]
#[Description('Import a menu from a JSON file for debugging the LLM pipeline')]
class MenuImportJson extends Command
{
    public function handle(SaveMenuAnalysisAction $action): int
    {
        $file = $this->argument('file');

        if (! File::exists($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $raw = File::get($file);
        $menuData = MenuJson::decodeMenuFromLlmText($raw);

        if (empty($menuData)) {
            $this->error('Could not parse JSON from the file.');

            return self::FAILURE;
        }

        $r = $menuData['restaurant'] ?? [];
        $restaurantName = $r['name'] ?? 'Unknown';
        $currency = $r['currency'] ?? '—';
        $lang = $r['primary_language'] ?? '—';

        $this->line('');
        $this->line("  <info>Restaurant:</info> {$restaurantName}   <info>Currency:</info> {$currency}   <info>Lang:</info> {$lang}");
        $this->line('');

        $tableRows = [];
        $totalItems = 0;
        $totalVariations = 0;
        $totalOptionGroups = 0;

        foreach (MenuJson::sections($menuData) as $section) {
            $items = $section['items'] ?? [];
            $variations = array_sum(array_map(fn ($i) => count($i['variations'] ?? []), $items));
            $options = array_sum(array_map(fn ($i) => count($i['options'] ?? []), $items));

            $totalItems += count($items);
            $totalVariations += $variations;
            $totalOptionGroups += $options;

            $tableRows[] = [
                $section['category_name'] ?? '—',
                count($items),
                $variations,
                $options,
            ];
        }

        $this->table(
            ['Section', 'Items', 'Variations', 'Option groups'],
            $tableRows,
        );

        $this->line("  Total: <info>{$totalItems}</info> items, <info>{$totalVariations}</info> variations, <info>{$totalOptionGroups}</info> option groups");
        $this->line('');

        if ($this->option('dry-run')) {
            $this->line('  <comment>Dry run — nothing written to DB.</comment>');

            return self::SUCCESS;
        }

        $restaurantId = $this->option('restaurant');

        if ($restaurantId) {
            $restaurant = Restaurant::find($restaurantId);
            if (! $restaurant) {
                $this->error("Restaurant #{$restaurantId} not found.");

                return self::FAILURE;
            }
        } else {
            $user = User::first();
            if (! $user) {
                $this->error('No users found. Create a user first or pass --restaurant=ID.');

                return self::FAILURE;
            }

            $restaurant = Restaurant::create(['created_by_user_id' => $user->id]);
            $this->line("  Created restaurant <info>#{$restaurant->id}</info>");
        }

        $menu = $action->handle($menuData, $restaurant->id, 1);

        if ($this->option('activate')) {
            $restaurant->menus()->where('id', '!=', $menu->id)->update(['is_active' => false]);
            $menu->update(['is_active' => true]);
            $this->line("  Menu <info>#{$menu->id}</info> marked as active.");
        } else {
            $this->line("  Menu <info>#{$menu->id}</info> created (inactive). Pass --activate to make it active.");
        }

        $this->line("  Restaurant ID: <info>{$restaurant->id}</info>");
        $this->line('');

        return self::SUCCESS;
    }
}
