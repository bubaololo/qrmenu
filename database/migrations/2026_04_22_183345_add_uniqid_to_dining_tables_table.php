<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_tables', function (Blueprint $table): void {
            $table->string('uniqid', 23)->nullable()->after('id');
        });

        foreach (DB::table('dining_tables')->select('id')->orderBy('id')->cursor() as $row) {
            DB::table('dining_tables')
                ->where('id', $row->id)
                ->update(['uniqid' => str_replace('.', '', uniqid('', true))]);
        }

        Schema::table('dining_tables', function (Blueprint $table): void {
            $table->string('uniqid', 23)->nullable(false)->change();
            $table->unique('uniqid', 'dining_tables_uniqid_unique');
        });
    }

    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table): void {
            $table->dropUnique('dining_tables_uniqid_unique');
            $table->dropColumn('uniqid');
        });
    }
};
