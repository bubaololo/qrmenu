<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Size-dependent add-on pricing. An `add` group may point at a (single-select)
 * DRIVER group; each of its options then carries a price per driver option
 * (e.g. "extra cheese" = +20k on Small, +40k on Large). Absent rows fall back
 * to the option's own flat `price`, so existing flat groups are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->foreignId('price_driver_group_id')->nullable()->after('parent_option_id')
                ->constrained('modifier_groups')->nullOnDelete();
        });

        Schema::create('modifier_option_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_id')->constrained('modifier_options')->cascadeOnDelete();
            $table->foreignId('driver_option_id')->constrained('modifier_options')->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->timestamps();
            $table->unique(['option_id', 'driver_option_id']);
            $table->index('driver_option_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modifier_option_prices');

        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->dropForeign(['price_driver_group_id']);
            $table->dropColumn('price_driver_group_id');
        });
    }
};
