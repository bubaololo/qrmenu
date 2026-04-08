<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_option_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->integer('min_select')->default(0);
            $table->integer('max_select')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_option_groups');
    }
};
