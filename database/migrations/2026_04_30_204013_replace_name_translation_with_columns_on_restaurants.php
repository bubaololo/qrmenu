<?php

use App\Models\Restaurant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->string('name')->nullable()->after('uniqid');
            $table->string('name_en')->nullable()->after('name');
        });

        $nameFieldId = DB::table('translation_fields')->where('name', 'name')->value('id');

        if ($nameFieldId !== null) {
            DB::statement(<<<'SQL'
                UPDATE restaurants r
                SET name = t.value
                FROM translations t
                WHERE t.translatable_type = ?
                  AND t.translatable_id   = r.id
                  AND t.field_id          = ?
                  AND t.is_initial        = true
            SQL, [Restaurant::class, $nameFieldId]);

            DB::statement(<<<'SQL'
                UPDATE restaurants r
                SET name_en = t.value
                FROM translations t
                WHERE t.translatable_type = ?
                  AND t.translatable_id   = r.id
                  AND t.field_id          = ?
                  AND t.locale            = 'en'
                  AND r.name_en IS NULL
            SQL, [Restaurant::class, $nameFieldId]);

            DB::table('translations')
                ->where('translatable_type', Restaurant::class)
                ->where('field_id', $nameFieldId)
                ->delete();
        }
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->dropColumn(['name', 'name_en']);
        });
    }
};
