<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep one menu per restaurant: the active one if any, otherwise the most recently created.
        $rows = DB::table('menus')->select('id', 'restaurant_id', 'is_active', 'created_at')->get();
        $byRestaurant = $rows->groupBy('restaurant_id');

        foreach ($byRestaurant as $restaurantId => $menus) {
            $keep = $menus->firstWhere('is_active', true) ?? $menus->sortByDesc('created_at')->first();
            $idsToDelete = $menus->pluck('id')->reject(fn ($id) => $id === $keep->id)->all();

            if ($idsToDelete !== []) {
                // Sections, items, option groups cascade through `menu_sections.menu_id` cascadeOnDelete.
                DB::table('menus')->whereIn('id', $idsToDelete)->delete();
            }
        }

        Schema::table('menus', function (Blueprint $table): void {
            $table->dropColumn('is_active');
            $table->unique('restaurant_id');
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table): void {
            $table->dropUnique(['restaurant_id']);
            $table->boolean('is_active')->default(false);
        });
    }
};
