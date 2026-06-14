<?php

namespace Tests\Feature;

use App\Enums\RestaurantUserRole;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ZoneTest extends TestCase
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
    public function unauthenticated_cannot_list_zones(): void
    {
        $restaurant = Restaurant::factory()->create();
        $this->getJson("/api/v1/restaurants/{$restaurant->id}/zones")->assertStatus(401);
    }

    #[Test]
    public function owner_can_list_zones(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        Zone::factory()->count(3)->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/restaurants/{$restaurant->id}/zones")
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function owner_can_create_zone(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);

        $this->actingAs($user)
            ->postJson("/api/v1/restaurants/{$restaurant->id}/zones", [
                'name' => 'Main Zone',
                'color' => '#FF5733',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'zones')
            ->assertJsonPath('data.attributes.color', '#FF5733');

        $this->assertDatabaseHas('zones', [
            'restaurant_id' => $restaurant->id,
            'color' => '#FF5733',
        ]);
    }

    #[Test]
    public function waiter_cannot_create_zone(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asWaiterOf($restaurant);

        $this->actingAs($user)
            ->postJson("/api/v1/restaurants/{$restaurant->id}/zones", ['name' => 'Test'])
            ->assertStatus(403);
    }

    #[Test]
    public function owner_can_update_zone(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $zone = Zone::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)
            ->putJson("/api/v1/zones/{$zone->id}", [
                'name' => 'Updated Zone',
                'color' => '#123456',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.color', '#123456');
    }

    #[Test]
    public function owner_can_delete_zone(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $zone = Zone::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/zones/{$zone->id}")
            ->assertStatus(204);

        $this->assertModelMissing($zone);
    }

    #[Test]
    public function rejects_zone_name_over_limit(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);

        $this->actingAs($user)
            ->postJson("/api/v1/restaurants/{$restaurant->id}/zones", [
                'name' => str_repeat('a', config('limits.zone_name') + 1),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    #[Test]
    public function accepts_zone_name_at_limit(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);

        $this->actingAs($user)
            ->postJson("/api/v1/restaurants/{$restaurant->id}/zones", [
                'name' => str_repeat('a', config('limits.zone_name')),
            ])
            ->assertStatus(201);
    }

    #[Test]
    public function cannot_access_other_restaurant_zone(): void
    {
        $restaurant1 = Restaurant::factory()->create();
        $restaurant2 = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant1);
        $zone = Zone::factory()->create(['restaurant_id' => $restaurant2->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/zones/{$zone->id}")
            ->assertStatus(403);
    }
}
