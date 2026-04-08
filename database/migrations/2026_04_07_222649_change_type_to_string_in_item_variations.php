<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL: drop enum constraint by changing the column type to varchar
        DB::statement(
            'ALTER TABLE item_variations ALTER COLUMN type TYPE varchar(100)'
        );
    }

    public function down(): void
    {
        // Restore enum — only works if existing values match; otherwise this will fail
        DB::statement(
            "ALTER TABLE item_variations ALTER COLUMN type TYPE varchar(100) USING type::varchar"
        );
    }
};
