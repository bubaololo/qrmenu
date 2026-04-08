<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE UNIQUE INDEX translations_one_initial_per_field
            ON translations (translatable_type, translatable_id, field)
            WHERE is_initial = true
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS translations_one_initial_per_field');
    }
};
