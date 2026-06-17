<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the split `menu_variations`/`menu_variation_options` + `menu_addons`
 * model with ONE recursive primitive: modifier groups + options.
 *
 *   - A "Size" axis  => group(pricing_mode=replace, single, min=max=1, required).
 *   - An "Extras"    => group(pricing_mode=add, multi, min=0, max=null).
 *
 * Selection constraints (min/max/required), per-item overrides (the junction),
 * per-option quantity, nesting (parent_option_id), and combos/inventory
 * (linked_menu_item_id) all live here. The quantity/charge/portion/linked
 * columns ship now but inert — phases 2 & 3 activate them with no further
 * item-table migration.
 *
 * Existing variations/add-ons are migrated (translations preserved). Orders
 * snapshot into the new recursive order_item_modifiers table going forward;
 * legacy order_items option columns are dropped (no orders carried options).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            // Nesting: when set, this group is revealed only by choosing that option.
            // FK added after modifier_options exists (self-referential cycle).
            $table->foreignId('parent_option_id')->nullable();
            $table->string('pricing_mode', 10)->default('add');     // replace | add
            $table->string('selection_type', 10)->default('single'); // single | multi | portion
            $table->unsignedSmallInteger('selection_min')->default(0); // authoritative for "required"
            $table->unsignedSmallInteger('selection_max')->nullable(); // null = unlimited
            $table->boolean('required')->default(false);               // convenience mirror of selection_min >= 1
            $table->unsignedSmallInteger('charge_above')->nullable();  // free N then charge (phase 2)
            $table->unsignedTinyInteger('portion_denominator')->default(1); // half-and-half (phase 3)
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('menu_id');
            $table->index('parent_option_id');
        });

        Schema::create('modifier_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('modifier_groups')->cascadeOnDelete();
            // Meaning depends on the group's pricing_mode: replace => absolute
            // (null falls back to menu_items.price_value); add => signed delta.
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('default_qty')->default(1); // phase 2
            $table->unsignedSmallInteger('max_qty')->default(1);     // phase 2 (>1 enables the stepper)
            // Combos + future inventory anchor (phase 3).
            $table->foreignId('linked_menu_item_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('group_id');
            $table->index('linked_menu_item_id');
        });

        // Self-referential nesting FK now that both tables exist.
        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->foreign('parent_option_id')->references('id')->on('modifier_options')->cascadeOnDelete();
        });

        // M:N item <-> group junction with per-item overrides.
        Schema::create('menu_item_modifier_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('modifier_groups')->cascadeOnDelete();
            $table->unsignedSmallInteger('selection_min_override')->nullable();
            $table->unsignedSmallInteger('selection_max_override')->nullable();
            $table->boolean('required_override')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->integer('sort_order')->default(0);
            $table->unique(['item_id', 'group_id']);
            $table->index('group_id');
        });

        // Recursive order snapshot — the order tree mirrors the catalog tree.
        // Denormalized *_snapshot columns make a line self-describing so history
        // survives option rename/delete; live FKs are for "reorder"/analytics only.
        Schema::create('order_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('order_item_modifiers')->cascadeOnDelete();
            $table->foreignId('modifier_group_id')->nullable()->constrained('modifier_groups')->nullOnDelete();
            $table->foreignId('modifier_option_id')->nullable()->constrained('modifier_options')->nullOnDelete();
            $table->string('group_name_snapshot')->nullable();
            $table->string('option_name_snapshot')->nullable();
            $table->string('pricing_mode_snapshot', 10)->nullable();
            $table->unsignedSmallInteger('qty')->default(1);
            $table->unsignedTinyInteger('portion_numerator')->nullable();
            $table->unsignedTinyInteger('portion_denominator')->nullable();
            $table->decimal('unit_price_snapshot', 10, 2)->nullable();
            $table->decimal('line_amount_snapshot', 10, 2)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('order_item_id');
            $table->index('parent_id');
        });

        if (Schema::hasTable('menu_variations')) {
            $this->migrateExistingData();
        }

        // Drop legacy order_items option columns (orders snapshot into
        // order_item_modifiers going forward; no orders carried options).
        if (Schema::hasColumn('order_items', 'variation_option_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign(['variation_option_id']);
            });
        }
        Schema::table('order_items', function (Blueprint $table) {
            foreach (['variation_option_id', 'variation_option_ids', 'selected_options'] as $col) {
                if (Schema::hasColumn('order_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('menu_item_variation');
        Schema::dropIfExists('menu_variation_options');
        Schema::dropIfExists('menu_variations');
        Schema::dropIfExists('menu_item_addon');
        Schema::dropIfExists('menu_addons');
    }

    /**
     * Migrate variations/options/add-ons into modifier groups/options,
     * preserving polymorphic translations by re-pointing them to the new ids.
     */
    private function migrateExistingData(): void
    {
        DB::transaction(function () {
            $now = now();
            $nameFieldId = DB::table('translation_fields')->where('name', 'name')->value('id');
            $groupForVariation = [];

            // 1. Each variation => replace/single/required group; its options => options.
            foreach (DB::table('menu_variations')->orderBy('id')->get() as $variation) {
                $groupId = DB::table('modifier_groups')->insertGetId([
                    'menu_id' => $variation->menu_id,
                    'pricing_mode' => 'replace',
                    'selection_type' => 'single',
                    'selection_min' => 1,
                    'selection_max' => 1,
                    'required' => true,
                    'portion_denominator' => 1,
                    'sort_order' => $variation->sort_order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $groupForVariation[$variation->id] = $groupId;

                DB::table('translations')
                    ->where('translatable_type', 'App\\Models\\MenuVariation')
                    ->where('translatable_id', $variation->id)
                    ->update([
                        'translatable_type' => 'App\\Models\\ModifierGroup',
                        'translatable_id' => $groupId,
                    ]);

                $options = DB::table('menu_variation_options')
                    ->where('variation_id', $variation->id)
                    ->orderBy('sort_order')
                    ->get();
                foreach ($options as $option) {
                    $optionId = DB::table('modifier_options')->insertGetId([
                        'group_id' => $groupId,
                        'price' => $option->price,
                        'is_default' => $option->is_default,
                        'default_qty' => 1,
                        'max_qty' => 1,
                        'sort_order' => $option->sort_order,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    DB::table('translations')
                        ->where('translatable_type', 'App\\Models\\MenuVariationOption')
                        ->where('translatable_id', $option->id)
                        ->update([
                            'translatable_type' => 'App\\Models\\ModifierOption',
                            'translatable_id' => $optionId,
                        ]);
                }
            }

            // 2. Variation item pivots => junction (no overrides — group defaults govern).
            foreach (DB::table('menu_item_variation')->get() as $pivot) {
                if (! isset($groupForVariation[$pivot->variation_id])) {
                    continue;
                }
                DB::table('menu_item_modifier_group')->insert([
                    'item_id' => $pivot->item_id,
                    'group_id' => $groupForVariation[$pivot->variation_id],
                    'is_hidden' => false,
                    'sort_order' => 0,
                ]);
            }

            // 3. Add-ons => one shared add-group per distinct add-on SET, per menu
            //    (preserves exactly what dedupeAttachAddons produced — items that
            //    shared an add-on subset share a group).
            foreach (DB::table('menus')->pluck('source_locale', 'id') as $menuId => $sourceLocale) {
                $menuAddons = DB::table('menu_addons')
                    ->where('menu_id', $menuId)
                    ->orderBy('sort_order')
                    ->get()
                    ->keyBy('id');
                if ($menuAddons->isEmpty()) {
                    continue;
                }

                $itemAddons = [];
                $pivotRows = DB::table('menu_item_addon')
                    ->join('menu_addons', 'menu_item_addon.addon_id', '=', 'menu_addons.id')
                    ->where('menu_addons.menu_id', $menuId)
                    ->select('menu_item_addon.item_id', 'menu_item_addon.addon_id')
                    ->get();
                foreach ($pivotRows as $row) {
                    $itemAddons[$row->item_id][] = $row->addon_id;
                }

                $sets = [];
                foreach ($itemAddons as $itemId => $addonIds) {
                    sort($addonIds);
                    $signature = implode(',', $addonIds);
                    $sets[$signature]['addon_ids'] = $addonIds;
                    $sets[$signature]['items'][] = $itemId;
                }

                foreach ($sets as $set) {
                    $groupId = DB::table('modifier_groups')->insertGetId([
                        'menu_id' => $menuId,
                        'pricing_mode' => 'add',
                        'selection_type' => 'multi',
                        'selection_min' => 0,
                        'selection_max' => null,
                        'required' => false,
                        'portion_denominator' => 1,
                        'sort_order' => 100,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    if ($nameFieldId && $sourceLocale && $sourceLocale !== 'mixed') {
                        DB::table('translations')->insert([
                            'translatable_type' => 'App\\Models\\ModifierGroup',
                            'translatable_id' => $groupId,
                            'field_id' => $nameFieldId,
                            'locale' => $sourceLocale,
                            'value' => 'Extras',
                            'is_initial' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    $sortIndex = 0;
                    foreach ($set['addon_ids'] as $addonId) {
                        $addon = $menuAddons[$addonId];
                        $optionId = DB::table('modifier_options')->insertGetId([
                            'group_id' => $groupId,
                            'price' => $addon->price,
                            'is_default' => false,
                            'default_qty' => 1,
                            'max_qty' => 1,
                            'sort_order' => $sortIndex++,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        // Copy (not move) — an add-on can appear in several sets.
                        $addonTranslations = DB::table('translations')
                            ->where('translatable_type', 'App\\Models\\MenuAddon')
                            ->where('translatable_id', $addonId)
                            ->get();
                        foreach ($addonTranslations as $translation) {
                            DB::table('translations')->insert([
                                'translatable_type' => 'App\\Models\\ModifierOption',
                                'translatable_id' => $optionId,
                                'field_id' => $translation->field_id,
                                'locale' => $translation->locale,
                                'value' => $translation->value,
                                'is_initial' => $translation->is_initial,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }
                    }

                    foreach ($set['items'] as $itemId) {
                        DB::table('menu_item_modifier_group')->insert([
                            'item_id' => $itemId,
                            'group_id' => $groupId,
                            'is_hidden' => false,
                            'sort_order' => 100,
                        ]);
                    }
                }

                // Drop the now-copied add-on translations.
                DB::table('translations')
                    ->where('translatable_type', 'App\\Models\\MenuAddon')
                    ->whereIn('translatable_id', $menuAddons->keys())
                    ->delete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_modifiers');

        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->dropForeign(['parent_option_id']);
        });
        Schema::dropIfExists('menu_item_modifier_group');
        Schema::dropIfExists('modifier_options');
        Schema::dropIfExists('modifier_groups');

        // Recreate the pre-migration option tables (shapes only; no data restore).
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

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('variation_option_id')->nullable()->after('menu_item_id')
                ->constrained('menu_variation_options')->nullOnDelete();
            $table->json('variation_option_ids')->nullable();
            $table->json('selected_options')->nullable();
        });
    }
};
