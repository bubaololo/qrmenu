<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Renumber tables sequentially within each zone (fixes any duplicates).
        DB::statement('
            UPDATE dining_tables
            SET number = subq.rn
            FROM (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY zone_id ORDER BY id) AS rn
                FROM dining_tables
            ) subq
            WHERE dining_tables.id = subq.id
        ');

        Schema::table('dining_tables', function (Blueprint $table) {
            $table->unique(['zone_id', 'number'], 'dining_tables_zone_id_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropUnique('dining_tables_zone_id_number_unique');
        });
    }
};
