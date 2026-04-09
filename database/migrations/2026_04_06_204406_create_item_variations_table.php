<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_option_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('menu_sections')->cascadeOnDelete();
            $table->string('type', 100)->nullable();
            $table->boolean('is_variation')->default(false);
            $table->boolean('required')->default(false);
            $table->boolean('allow_multiple')->default(false);
            $table->integer('min_select')->default(0);
            $table->integer('max_select')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_option_groups');
    }
};
