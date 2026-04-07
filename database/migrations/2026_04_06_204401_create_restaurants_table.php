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
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name_local');
            $table->string('name_en')->nullable();
            $table->string('address_local')->nullable();
            $table->string('address_en')->nullable();
            $table->string('district')->nullable();
            $table->string('city');
            $table->string('province')->nullable();
            $table->string('country');
            $table->string('phone')->nullable();
            $table->string('phone2')->nullable();
            $table->char('currency', 3)->default('USD');
            $table->string('primary_language', 10)->default('en');
            $table->json('opening_hours')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
