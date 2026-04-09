<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_option_group_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('menu_option_groups')->cascadeOnDelete();
            $table->decimal('price_adjust', 10, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_option_group_options');
    }
};
