<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Switch restaurants.created_by_user_id from cascadeOnDelete to restrictOnDelete.
     *
     * Prevents accidental loss of restaurants when a user is deleted. Co-owners from
     * restaurant_users continue to manage the restaurant; deleting the original creator
     * now requires either transferring ownership or deleting the restaurant explicitly.
     */
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->foreign('created_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->foreign('created_by_user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
