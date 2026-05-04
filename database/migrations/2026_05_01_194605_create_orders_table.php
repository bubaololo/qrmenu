<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->uuid('guest_token');
            $table->string('status', 20)->default('pending');
            $table->text('note')->nullable();
            $table->timestamp('placed_at')->useCurrent();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'placed_at']);
            $table->index('guest_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
