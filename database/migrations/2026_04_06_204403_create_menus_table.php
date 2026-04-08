<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('source_locale', 10)->nullable();
            $table->date('detected_date')->nullable();
            $table->integer('source_images_count')->default(0);
            $table->boolean('is_active')->default(false)->index();
            $table->foreignId('created_from_menu_id')->nullable()->constrained('menus')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
