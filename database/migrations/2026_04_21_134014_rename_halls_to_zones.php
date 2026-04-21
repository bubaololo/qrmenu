<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('halls', 'zones');

        Schema::table('dining_tables', function (Blueprint $table) {
            $table->renameColumn('hall_id', 'zone_id');
        });

        DB::table('translations')
            ->where('translatable_type', 'App\\Models\\Hall')
            ->update(['translatable_type' => 'App\\Models\\Zone']);
    }

    public function down(): void
    {
        Schema::rename('zones', 'halls');

        Schema::table('dining_tables', function (Blueprint $table) {
            $table->renameColumn('zone_id', 'hall_id');
        });

        DB::table('translations')
            ->where('translatable_type', 'App\\Models\\Zone')
            ->update(['translatable_type' => 'App\\Models\\Hall']);
    }
};
