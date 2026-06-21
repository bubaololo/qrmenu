<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Email verification is being introduced after users already exist. Those
     * accounts were created while the feature was off, so their email_verified_at
     * is null — grandfather them in so the new `verified` gate doesn't lock them
     * out. Only sign-ups from this point forward must verify.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    /**
     * Irreversible: backfilled rows are indistinguishable from genuinely
     * verified ones, so there is nothing safe to roll back.
     */
    public function down(): void
    {
        //
    }
};
