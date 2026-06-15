<?php

use App\Models\MenuOptionGroup;
use App\Models\MenuOptionGroupOption;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lift option groups from section scope to menu scope and replace the
     * is_variation boolean with a kind enum. Existing option data is dropped
     * (acceptable) so the schema can change without FK conflicts; the rest of
     * the database (menus, sections, items, orders) is untouched.
     */
    public function up(): void
    {
        // Wipe option data only (losing options/add-ons is acceptable here).
        DB::table('menu_item_option_group')->delete();
        DB::table('menu_option_group_options')->delete();
        DB::table('menu_option_groups')->delete();

        DB::table('translations')
            ->whereIn('translatable_type', [MenuOptionGroup::class, MenuOptionGroupOption::class])
            ->delete();

        Schema::table('menu_option_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('section_id');
            $table->foreignId('menu_id')->after('id')->constrained('menus')->cascadeOnDelete();

            $table->dropColumn('is_variation');
            $table->string('kind', 20)->default('addon')->after('menu_id');
        });
    }

    public function down(): void
    {
        DB::table('menu_item_option_group')->delete();
        DB::table('menu_option_group_options')->delete();
        DB::table('menu_option_groups')->delete();

        Schema::table('menu_option_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('menu_id');
            $table->foreignId('section_id')->after('id')->constrained('menu_sections')->cascadeOnDelete();

            $table->dropColumn('kind');
            $table->boolean('is_variation')->default(false)->after('type');
        });
    }
};
