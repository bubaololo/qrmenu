<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freeze the dish name + base price on each order line (like the modifier
 * snapshots) so a placed order survives later menu renames/deletes.
 * base_price = the dish price before add-on deltas (replace option ?? price_value).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('menu_item_name_snapshot')->nullable()->after('menu_item_id');
            $table->decimal('base_price_snapshot', 10, 2)->nullable()->after('menu_item_name_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['menu_item_name_snapshot', 'base_price_snapshot']);
        });
    }
};
