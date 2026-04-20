<?php

namespace Tests\Feature;

use App\Enums\RestaurantUserRole;
use App\Models\Hall;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HallTest extends TestCase
{
    use RefreshDatabase;

    private function asOwnerOf(Restaurant $restaurant): User
    {
        $user = User::factory()->create();
        $restaurant->users()->attach($user, ['role' => RestaurantUserRole::Owner->value]);

        return $user;
    }

    private function asWaiterOf(Restaurant $restaurant): User
    {
        $user = User::factory()->create();
        $restaurant->users()->attach($user, ['role' => RestaurantUserRole::Waiter->value]);

        return $user;
    }

    #[Test]
    public function unauthenticated_cannot_list_halls(): void
    {
        $restaurant = Restaurant::factory()->create();
        $this->getJson("/api/v1/restaurants/{$restaurant->id}/halls")->assertStatus(401);
    }

    #[Test]
    public function owner_can_list_halls(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        Hall::factory()->count(3)->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/restaurants/{$restaurant->id}/halls")
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function owner_can_create_hall(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);

        $this->actingAs($user)
            ->postJson("/api/v1/restaurants/{$restaurant->id}/halls", [
                'name' => 'Main Hall',
                'color' => '#FF5733',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'halls')
            ->assertJsonPath('data.attributes.color', '#FF5733');

        $this->assertDatabaseHas('halls', [
            'restaurant_id' => $restaurant->id,
            'color' => '#FF5733',
        ]);
    }

    #[Test]
    public function waiter_cannot_create_hall(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asWaiterOf($restaurant);

        $this->actingAs($user)
            ->postJson("/api/v1/restaurants/{$restaurant->id}/halls", ['name' => 'Test'])
            ->assertStatus(403);
    }

    #[Test]
    public function owner_can_update_hall(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $hall = Hall::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)
            ->putJson("/api/v1/halls/{$hall->id}", [
                'name' => 'Updated Hall',
                'color' => '#123456',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.color', '#123456');
    }

    #[Test]
    public function owner_can_delete_hall(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $hall = Hall::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/halls/{$hall->id}")
            ->assertStatus(204);

        $this->assertModelMissing($hall);
    }

    #[Test]
    public function cannot_access_other_restaurant_hall(): void
    {
        $restaurant1 = Restaurant::factory()->create();
        $restaurant2 = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant1);
        $hall = Hall::factory()->create(['restaurant_id' => $restaurant2->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/halls/{$hall->id}")
            ->assertStatus(403);
    }
}
