<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_shapes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
        });

        DB::table('table_shapes')->insert([
            ['name' => 'round',       'label' => 'Круглый',       'sort_order' => 1],
            ['name' => 'square',      'label' => 'Квадратный',    'sort_order' => 2],
            ['name' => 'rectangular', 'label' => 'Прямоугольный', 'sort_order' => 3],
            ['name' => 'bar_counter', 'label' => 'Барная стойка', 'sort_order' => 4],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('table_shapes');
    }
};
