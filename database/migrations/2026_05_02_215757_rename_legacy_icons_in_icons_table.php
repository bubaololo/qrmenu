<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Map of legacy icon names → new whitelisted names.
     * Anything in icons.name not in this map and not already in the new whitelist
     * gets deleted (FK menu_sections.icon_id is nullOnDelete, so sections become iconless).
     */
    private const RENAMES = [
        'noodles' => 'noodle-bowl',
        'rice-bowl-01' => 'rice-bowl',
        'dim-sum-01' => 'dim-sum',
        'sushi-01' => 'sushi',
        'pizza-01' => 'pizza',
        'hamburger-01' => 'burger',
        'taco-01' => 'wrap',
        'french-fries-01' => 'french-fries',
        'chicken-thighs' => 'chicken-leg',
        'bbq-grill' => 'grill',
        'fish-food' => 'fish',
        'pot-01' => 'soup-pot',
        'bread-01' => 'baguette',
        'vegetarian-food' => 'salad',
        'natural-food' => 'healthy-food',
        'cupcake-01' => 'cupcake',
        'cheese-cake-01' => 'cake',
        'doughnut' => 'donut',
        'ice-cream-01' => 'ice-cream',
        'ice-cream-02' => 'ice-cream',
        'coffee-01' => 'iced-coffee',
        'coffee-02' => 'hot-coffee',
        'bubble-tea-01' => 'bubble-tea',
        'soft-drink-01' => 'soft-drink',
        'drink' => 'cocktail',
        'milk-bottle' => 'milk',
        'milk-coconut' => 'coconut-milk',
    ];

    public function up(): void
    {
        $whitelist = config('food_icons.allowed', []);

        DB::transaction(function () use ($whitelist): void {
            // 1. Apply renames. Skip when target already exists to avoid unique-name collisions:
            //    re-point sections to the existing target icon, then delete the legacy row.
            foreach (self::RENAMES as $old => $new) {
                $oldRow = DB::table('icons')->where('name', $old)->first();
                if ($oldRow === null) {
                    continue;
                }

                $newRow = DB::table('icons')->where('name', $new)->first();

                if ($newRow === null) {
                    DB::table('icons')->where('id', $oldRow->id)->update(['name' => $new]);

                    continue;
                }

                DB::table('menu_sections')
                    ->where('icon_id', $oldRow->id)
                    ->update(['icon_id' => $newRow->id]);

                DB::table('icons')->where('id', $oldRow->id)->delete();
            }

            // 2. Drop any icon row whose name is not in the new whitelist.
            //    FK menu_sections.icon_id is nullOnDelete → no orphan FKs.
            if ($whitelist !== []) {
                DB::table('icons')->whereNotIn('name', $whitelist)->delete();
            }
        });
    }

    public function down(): void
    {
        // Reverse renames best-effort. Deleted off-whitelist rows are not restored.
        DB::transaction(function (): void {
            foreach (self::RENAMES as $old => $new) {
                DB::table('icons')->where('name', $new)->update(['name' => $old]);
            }
        });
    }
};
