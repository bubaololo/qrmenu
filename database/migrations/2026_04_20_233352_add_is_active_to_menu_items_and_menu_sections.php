<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_sections', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('icon_id');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('menu_sections', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
