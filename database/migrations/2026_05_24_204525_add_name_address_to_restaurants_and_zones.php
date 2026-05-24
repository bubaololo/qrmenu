<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->string('name', 255)->nullable()->after('created_by_user_id');
            $table->text('address')->nullable()->after('name');
        });

        Schema::table('zones', function (Blueprint $table): void {
            $table->string('name', 255)->nullable()->after('restaurant_id');
        });

        // Backfill from translations. Use is_initial=true rows — non-initial
        // Restaurant translations were machine-generated and become irrelevant
        // once Restaurant is no longer translatable.
        DB::statement(<<<'SQL'
            UPDATE restaurants r
            SET name = t.value
            FROM translations t
            JOIN translation_fields f ON f.id = t.field_id AND f.name = 'name'
            WHERE t.translatable_type = 'App\Models\Restaurant'
              AND t.translatable_id = r.id
              AND t.is_initial = true
        SQL);

        DB::statement(<<<'SQL'
            UPDATE restaurants r
            SET address = t.value
            FROM translations t
            JOIN translation_fields f ON f.id = t.field_id AND f.name = 'address'
            WHERE t.translatable_type = 'App\Models\Restaurant'
              AND t.translatable_id = r.id
              AND t.is_initial = true
        SQL);

        DB::statement(<<<'SQL'
            UPDATE zones z
            SET name = t.value
            FROM translations t
            JOIN translation_fields f ON f.id = t.field_id AND f.name = 'name'
            WHERE t.translatable_type = 'App\Models\Zone'
              AND t.translatable_id = z.id
              AND t.is_initial = true
        SQL);
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->dropColumn(['name', 'address']);
        });

        Schema::table('zones', function (Blueprint $table): void {
            $table->dropColumn('name');
        });
    }
};
