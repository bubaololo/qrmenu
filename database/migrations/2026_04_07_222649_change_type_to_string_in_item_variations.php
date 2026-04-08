<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the enum check constraint if it exists (safe no-op when already removed)
        DB::statement('ALTER TABLE item_variations DROP CONSTRAINT IF EXISTS item_variations_type_check');
        // Change the column type to unrestricted varchar (no-op if already varchar)
        DB::statement('ALTER TABLE item_variations ALTER COLUMN type TYPE varchar(100)');
    }

    public function down(): void
    {
        // Cannot safely restore enum without knowing existing values; leave as varchar
    }
};
