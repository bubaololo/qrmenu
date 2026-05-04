<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Cascade rather than restrict: cleanly resolves parallel-cascade
            // races when a whole restaurant is deleted (menu_items and orders
            // both cascade through different chains and converge on order_items).
            // Individual menu_item deletion is rare — admins disable via is_active.
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variation_option_id')->nullable()
                ->constrained('menu_option_group_options')
                ->nullOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->char('currency', 3);
            $table->json('selected_options')->nullable();
            $table->string('kitchen_status', 20)->default('waiting');
            $table->timestamp('started_cooking_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('kitchen_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
