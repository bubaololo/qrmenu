<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->morphs('translatable');
            $table->foreignId('locale_id')->constrained('locales')->cascadeOnDelete();
            $table->string('field', 100);
            $table->text('value');
            $table->boolean('is_initial')->default(false);
            $table->timestamps();

            $table->unique(['translatable_type', 'translatable_id', 'locale_id', 'field']);
            $table->index('locale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
