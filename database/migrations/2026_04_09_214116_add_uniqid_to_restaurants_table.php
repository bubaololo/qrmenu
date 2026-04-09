<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('uniqid', 12)->nullable()->after('id');
        });

        DB::table('restaurants')->whereNull('uniqid')->eachById(function ($row) {
            DB::table('restaurants')->where('id', $row->id)->update([
                'uniqid' => Str::random(8),
            ]);
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('uniqid', 12)->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('uniqid');
        });
    }
};
