<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Rename is_active → is_orderable. Existing inactive rows keep their
        //    "can't order" state; new rows default to true (orderable).
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->renameColumn('is_active', 'is_orderable');
        });

        // 2. Add is_visible. Backfill from the previous flag so currently
        //    hidden-and-disabled rows stay hidden after the split (admins can
        //    later flip is_visible=true to surface an "out of stock" item).
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->boolean('is_visible')->default(true)->after('is_orderable');
        });
        DB::statement('UPDATE menu_items SET is_visible = is_orderable');
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropColumn('is_visible');
        });
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->renameColumn('is_orderable', 'is_active');
        });
    }
};
