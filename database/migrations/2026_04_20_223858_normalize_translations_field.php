<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create translation_fields lookup table
        Schema::create('translation_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        // 2. Populate from distinct field values in translations
        DB::statement('
            INSERT INTO translation_fields (name, created_at, updated_at)
            SELECT DISTINCT field, NOW(), NOW()
            FROM translations
        ');

        // 3. Add nullable field_id column
        Schema::table('translations', function (Blueprint $table) {
            $table->foreignId('field_id')->nullable()->after('translatable_type')
                ->constrained('translation_fields')->cascadeOnDelete();
        });

        // 4. Backfill field_id
        DB::statement('
            UPDATE translations
            SET field_id = (
                SELECT id FROM translation_fields
                WHERE translation_fields.name = translations.field
            )
        ');

        // 5. Make field_id NOT NULL
        Schema::table('translations', function (Blueprint $table) {
            $table->foreignId('field_id')->nullable(false)->change();
        });

        // 6. Drop old constraints that include the string `field` column
        Schema::table('translations', function (Blueprint $table) {
            $table->dropUnique(['translatable_type', 'translatable_id', 'locale', 'field']);
        });

        // 7. Drop partial unique index on string field
        DB::statement('DROP INDEX IF EXISTS translations_one_initial_per_field');

        // 8. Drop the old string column
        Schema::table('translations', function (Blueprint $table) {
            $table->dropColumn('field');
        });

        // 9. Add new unique constraint using field_id
        Schema::table('translations', function (Blueprint $table) {
            $table->unique(['translatable_type', 'translatable_id', 'locale', 'field_id']);
        });

        // 10. Recreate partial unique index using field_id
        DB::statement('
            CREATE UNIQUE INDEX translations_one_initial_per_field
            ON translations (translatable_type, translatable_id, field_id)
            WHERE is_initial = true
        ');
    }

    public function down(): void
    {
        // 1. Re-add string field column (nullable for backfill)
        Schema::table('translations', function (Blueprint $table) {
            $table->string('field', 100)->nullable()->after('locale');
        });

        // 2. Backfill field string from translation_fields
        DB::statement('
            UPDATE translations
            SET field = (
                SELECT name FROM translation_fields
                WHERE translation_fields.id = translations.field_id
            )
        ');

        // 3. Make field NOT NULL
        Schema::table('translations', function (Blueprint $table) {
            $table->string('field', 100)->nullable(false)->change();
        });

        // 4. Drop new constraints
        Schema::table('translations', function (Blueprint $table) {
            $table->dropUnique(['translatable_type', 'translatable_id', 'locale', 'field_id']);
        });

        DB::statement('DROP INDEX IF EXISTS translations_one_initial_per_field');

        // 5. Drop field_id FK and column
        Schema::table('translations', function (Blueprint $table) {
            $table->dropForeign(['field_id']);
            $table->dropColumn('field_id');
        });

        // 6. Restore original constraints on string field
        Schema::table('translations', function (Blueprint $table) {
            $table->unique(['translatable_type', 'translatable_id', 'locale', 'field']);
        });

        DB::statement('
            CREATE UNIQUE INDEX translations_one_initial_per_field
            ON translations (translatable_type, translatable_id, field)
            WHERE is_initial = true
        ');

        // 7. Drop translation_fields table
        Schema::dropIfExists('translation_fields');
    }
};
