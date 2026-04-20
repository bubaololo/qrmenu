<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('image');
            $table->string('google_maps_url')->nullable()->after('logo');
        });

        DB::statement('ALTER TABLE restaurants ADD COLUMN coordinates point NULL');
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['logo', 'google_maps_url', 'coordinates']);
        });
    }
};
