<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->foreignId('table_shape_id')->nullable()->constrained('table_shapes');
        });

        DB::statement('
            UPDATE dining_tables dt
            SET table_shape_id = ts.id
            FROM table_shapes ts
            WHERE ts.name = dt.shape
        ');

        Schema::table('dining_tables', function (Blueprint $table) {
            $table->foreignId('table_shape_id')->nullable(false)->change();
            $table->dropColumn('shape');
        });
    }

    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->string('shape')->default('square');
        });

        DB::statement('
            UPDATE dining_tables dt
            SET shape = ts.name
            FROM table_shapes ts
            WHERE ts.id = dt.table_shape_id
        ');

        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('table_shape_id');
        });
    }
};
