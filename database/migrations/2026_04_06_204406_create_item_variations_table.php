<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->string('type', 100)->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('allow_multiple')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_variations');
    }
};
