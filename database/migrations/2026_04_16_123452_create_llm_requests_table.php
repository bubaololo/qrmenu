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
        Schema::create('llm_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_analysis_id')->nullable()->constrained('menu_analyses')->nullOnDelete();
            $table->string('provider', 50);
            $table->string('model', 100);
            $table->unsignedTinyInteger('tier_position')->default(0);
            $table->string('status', 20);
            $table->unsignedTinyInteger('image_count');
            $table->unsignedInteger('duration_ms');
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('response_length')->nullable();
            $table->string('finish_reason', 30)->nullable();
            $table->string('error_class', 150)->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('prompt_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_requests');
    }
};
