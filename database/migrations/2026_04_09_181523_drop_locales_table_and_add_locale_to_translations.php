<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add locale string column (nullable first for backfill)
        Schema::table('translations', function (Blueprint $table): void {
            $table->string('locale', 10)->nullable()->after('locale_id');
        });

        // 2. Backfill from locales table
        DB::statement('UPDATE translations SET locale = (SELECT code FROM locales WHERE locales.id = translations.locale_id)');

        // 3. Make NOT NULL
        Schema::table('translations', function (Blueprint $table): void {
            $table->string('locale', 10)->nullable(false)->change();
        });

        // 4. Drop unique constraint, FK, and old column
        Schema::table('translations', function (Blueprint $table): void {
            $table->dropUnique(['translatable_type', 'translatable_id', 'locale_id', 'field']);
            $table->dropForeign(['locale_id']);
            $table->dropColumn('locale_id');
        });

        // 5. Add new unique constraint using locale string
        Schema::table('translations', function (Blueprint $table): void {
            $table->unique(['translatable_type', 'translatable_id', 'locale', 'field']);
            $table->index('locale');
        });

        // 6. Drop locales table
        Schema::dropIfExists('locales');
    }

    public function down(): void
    {
        // Recreate locales table
        Schema::create('locales', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->timestamps();
        });

        // Backfill locales from distinct values in translations
        $locales = DB::table('translations')->select('locale')->distinct()->pluck('locale');
        foreach ($locales as $code) {
            DB::table('locales')->insert(['code' => $code, 'name' => $code, 'created_at' => now(), 'updated_at' => now()]);
        }

        // Add locale_id column
        Schema::table('translations', function (Blueprint $table): void {
            $table->dropUnique(['translatable_type', 'translatable_id', 'locale', 'field']);
            $table->dropIndex(['locale']);
            $table->unsignedBigInteger('locale_id')->nullable()->after('translatable_id');
        });

        // Backfill locale_id
        DB::statement('UPDATE translations SET locale_id = (SELECT id FROM locales WHERE locales.code = translations.locale)');

        // Restore FK and constraints
        Schema::table('translations', function (Blueprint $table): void {
            $table->unsignedBigInteger('locale_id')->nullable(false)->change();
            $table->foreign('locale_id')->references('id')->on('locales')->cascadeOnDelete();
            $table->unique(['translatable_type', 'translatable_id', 'locale_id', 'field']);
        });

        // Drop locale string column
        Schema::table('translations', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
