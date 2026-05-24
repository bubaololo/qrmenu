<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Safety net: any zone without a backfilled name gets a placeholder.
        // In normal flow the previous migration filled every row from
        // is_initial=true translations.
        DB::statement("UPDATE zones SET name = 'Zone #' || id WHERE name IS NULL");

        Schema::table('zones', function (Blueprint $table): void {
            $table->string('name', 255)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table): void {
            $table->string('name', 255)->nullable()->change();
        });
    }
};
