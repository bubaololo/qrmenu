<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RestrictUserDeleteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_deleting_user_with_owned_restaurant_is_blocked(): void
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['created_by_user_id' => $user->id]);

        // Wrap in a nested transaction so the FK violation rolls back the
        // savepoint rather than aborting the outer RefreshDatabase transaction.
        DB::beginTransaction();
        $thrown = false;
        try {
            $user->delete();
        } catch (QueryException) {
            $thrown = true;
        } finally {
            DB::rollBack();
        }

        $this->assertTrue($thrown, 'Expected a QueryException from FK restriction.');
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('restaurants', ['id' => $restaurant->id]);
    }

    #[Test]
    public function test_deleting_user_without_owned_restaurants_succeeds(): void
    {
        $user = User::factory()->create();

        $user->delete();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
