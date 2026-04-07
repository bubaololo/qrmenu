<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('menu_sections')->cascadeOnDelete();
            $table->string('name_local');
            $table->string('name_en')->nullable();
            $table->text('description_local')->nullable();
            $table->text('description_en')->nullable();
            $table->boolean('starred')->default(false);
            $table->enum('price_type', ['fixed', 'range', 'from', 'variable'])->default('fixed');
            $table->decimal('price_value', 10, 2)->nullable();
            $table->decimal('price_min', 10, 2)->nullable();
            $table->decimal('price_max', 10, 2)->nullable();
            $table->string('price_unit')->nullable();
            $table->string('price_unit_en')->nullable();
            $table->string('price_original_text')->default('');
            $table->json('image_bbox')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
