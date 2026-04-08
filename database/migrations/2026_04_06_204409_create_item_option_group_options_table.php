<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_option_group_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('item_option_groups')->cascadeOnDelete();
            $table->decimal('price_adjust', 10, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_option_group_options');
    }
};
