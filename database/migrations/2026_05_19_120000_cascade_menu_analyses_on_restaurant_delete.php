<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Switch menu_analyses.restaurant_id from nullOnDelete to cascadeOnDelete.
     *
     * MenuAnalysisObserver deletes the linked image files. With cascadeOnDelete
     * Eloquent does not emit events for child rows, so the parent
     * RestaurantObserver collects file paths from menu_analyses before delete.
     */
    public function up(): void
    {
        Schema::table('menu_analyses', function (Blueprint $table) {
            $table->dropForeign(['restaurant_id']);
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('menu_analyses', function (Blueprint $table) {
            $table->dropForeign(['restaurant_id']);
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->nullOnDelete();
        });
    }
};
