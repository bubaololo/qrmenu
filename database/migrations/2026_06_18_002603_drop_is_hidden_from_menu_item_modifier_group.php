<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the per-item `is_hidden` override on the modifier-group pivot. Hiding a
 * group on a single dish is equivalent to simply not attaching it, so the flag
 * was never surfaced in the editor UI and is always false — remove it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_item_modifier_group', function (Blueprint $table): void {
            $table->dropColumn('is_hidden');
        });
    }

    public function down(): void
    {
        Schema::table('menu_item_modifier_group', function (Blueprint $table): void {
            $table->boolean('is_hidden')->default(false);
        });
    }
};
