<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('translations')
            ->whereIn('translatable_type', [
                'App\\Models\\Restaurant',
                'App\\Models\\Zone',
            ])
            ->delete();
    }

    public function down(): void
    {
        // Backfill is destructive — the previous migration's down() removes
        // the name/address columns, which loses the source data needed here.
    }
};
