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
            $table->string('address', 500)->nullable()->after('uniqid');
        });

        $addressFieldId = DB::table('translation_fields')->where('name', 'address')->value('id');

        if ($addressFieldId !== null) {
            DB::statement(<<<'SQL'
                UPDATE restaurants r
                SET address = t.value
                FROM translations t
                WHERE t.translatable_type = ?
                  AND t.translatable_id   = r.id
                  AND t.field_id          = ?
                  AND t.is_initial        = true
            SQL, [Restaurant::class, $addressFieldId]);

            DB::table('translations')
                ->where('translatable_type', Restaurant::class)
                ->where('field_id', $addressFieldId)
                ->delete();
        }

        Schema::table('restaurants', function (Blueprint $table): void {
            $table->dropColumn(['city', 'country']);
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->dropColumn('address');
        });
    }
};
