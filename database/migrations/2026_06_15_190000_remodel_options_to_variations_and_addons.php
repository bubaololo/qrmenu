<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the generic "option group with selection constraints" model with two
     * purpose-built shapes:
     *   - VARIATIONS: a pick-exactly-one axis (Size, etc.); option price is ABSOLUTE.
     *   - ADD-ONS: atomic, additive extras (pick any); price is a DELTA.
     * Drops the `type`/`kind`/`required`/`allow_multiple`/`min_select`/`max_select`
     * fields and the overloaded `price_adjust`. Existing option data is disposable
     * (re-created by analysis); orders' option references are cleared.
     */
    public function up(): void
    {
        // Detach the order_items FK that points at the old options table so the
        // table can be dropped; null any stale references (dev orders only).
        if (Schema::hasColumn('order_items', 'variation_option_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign(['variation_option_id']);
            });
            DB::table('order_items')->update(['variation_option_id' => null, 'selected_options' => null]);
        }

        // Wipe legacy option translations + tables.
        DB::table('translations')
            ->whereIn('translatable_type', [
                'App\\Models\\MenuOptionGroup',
                'App\\Models\\MenuOptionGroupOption',
            ])
            ->delete();

        Schema::dropIfExists('menu_item_option_group');
        Schema::dropIfExists('menu_option_group_options');
        Schema::dropIfExists('menu_option_groups');

        // ─── Variations (grouped, pick-exactly-one, absolute price) ───
        Schema::create('menu_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('menu_variation_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variation_id')->constrained('menu_variations')->cascadeOnDelete();
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('menu_item_variation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignId('variation_id')->constrained('menu_variations')->cascadeOnDelete();
            $table->unique(['item_id', 'variation_id']);
        });

        // ─── Add-ons (atomic, additive price) ───
        Schema::create('menu_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('menu_item_addon', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained('menu_addons')->cascadeOnDelete();
            $table->unique(['item_id', 'addon_id']);
        });

        // Repoint the order line FK to the new variant-option table.
        if (Schema::hasColumn('order_items', 'variation_option_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->foreign('variation_option_id')
                    ->references('id')->on('menu_variation_options')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_items', 'variation_option_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign(['variation_option_id']);
            });
        }

        Schema::dropIfExists('menu_item_addon');
        Schema::dropIfExists('menu_addons');
        Schema::dropIfExists('menu_item_variation');
        Schema::dropIfExists('menu_variation_options');
        Schema::dropIfExists('menu_variations');

        Schema::create('menu_option_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->string('type', 100)->nullable();
            $table->string('kind', 20)->default('addon');
            $table->boolean('required')->default(false);
            $table->boolean('allow_multiple')->default(false);
            $table->integer('min_select')->default(0);
            $table->integer('max_select')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('menu_option_group_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('menu_option_groups')->cascadeOnDelete();
            $table->decimal('price_adjust', 10, 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('menu_item_option_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('menu_option_groups')->cascadeOnDelete();
            $table->unique(['item_id', 'group_id']);
        });

        if (Schema::hasColumn('order_items', 'variation_option_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->foreign('variation_option_id')
                    ->references('id')->on('menu_option_group_options')
                    ->nullOnDelete();
            });
        }
    }
};
