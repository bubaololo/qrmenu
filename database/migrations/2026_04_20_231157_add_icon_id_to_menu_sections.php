<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Seed icons from existing category_icon values (svg stays empty until populated)
        $names = DB::table('menu_sections')
            ->whereNotNull('category_icon')
            ->distinct()
            ->pluck('category_icon');

        foreach ($names as $name) {
            DB::table('icons')->insertOrIgnore([
                'name' => $name,
                'svg' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Add nullable icon_id FK
        Schema::table('menu_sections', function (Blueprint $table) {
            $table->foreignId('icon_id')->nullable()->after('sort_order')
                ->constrained('icons')->nullOnDelete();
        });

        // 3. Backfill icon_id from category_icon
        DB::statement('
            UPDATE menu_sections
            SET icon_id = icons.id
            FROM icons
            WHERE icons.name = menu_sections.category_icon
        ');

        // 4. Drop category_icon
        Schema::table('menu_sections', function (Blueprint $table) {
            $table->dropColumn('category_icon');
        });
    }

    public function down(): void
    {
        // 1. Re-add category_icon
        Schema::table('menu_sections', function (Blueprint $table) {
            $table->string('category_icon', 64)->nullable()->after('sort_order');
        });

        // 2. Backfill from icon name
        DB::statement('
            UPDATE menu_sections
            SET category_icon = icons.name
            FROM icons
            WHERE icons.id = menu_sections.icon_id
        ');

        // 3. Drop FK and icon_id
        Schema::table('menu_sections', function (Blueprint $table) {
            $table->dropForeign(['icon_id']);
            $table->dropColumn('icon_id');
        });
    }
};
