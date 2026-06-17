<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An order line can pick one option per variation axis (e.g. Size AND
     * Temperature), so the chosen options are a SET. `variation_option_ids`
     * holds the full set; the scalar `variation_option_id` is kept as a
     * back-compat mirror of the PRIMARY (lowest sort_order) axis option.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->json('variation_option_ids')->nullable()->after('variation_option_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('variation_option_ids');
        });
    }
};
