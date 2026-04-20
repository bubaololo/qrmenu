<?php

namespace Tests\Feature;

use App\Enums\DiningTableShape;
use App\Enums\RestaurantUserRole;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiningTableTest extends TestCase
{
    use RefreshDatabase;

    private function asOwnerOf(Restaurant $restaurant): User
    {
        $user = User::factory()->create();
        $restaurant->users()->attach($user, ['role' => RestaurantUserRole::Owner->value]);

        return $user;
    }

    private function hallForRestaurant(Restaurant $restaurant): Hall
    {
        return Hall::factory()->create(['restaurant_id' => $restaurant->id]);
    }

    #[Test]
    public function unauthenticated_cannot_list_tables(): void
    {
        $hall = Hall::factory()->create();
        $this->getJson("/api/v1/halls/{$hall->id}/tables")->assertStatus(401);
    }

    #[Test]
    public function owner_can_list_tables(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $hall = $this->hallForRestaurant($restaurant);
        DiningTable::factory()->count(4)->create(['hall_id' => $hall->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/halls/{$hall->id}/tables")
            ->assertStatus(200)
            ->assertJsonCount(4, 'data');
    }

    #[Test]
    public function owner_can_create_table(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $hall = $this->hallForRestaurant($restaurant);

        $this->actingAs($user)
            ->postJson("/api/v1/halls/{$hall->id}/tables", [
                'number' => 5,
                'capacity' => 4,
                'shape' => DiningTableShape::Round->value,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'dining_tables')
            ->assertJsonPath('data.attributes.number', 5)
            ->assertJsonPath('data.attributes.shape', 'round');

        $this->assertDatabaseHas('dining_tables', [
            'hall_id' => $hall->id,
            'number' => 5,
            'capacity' => 4,
            'shape' => 'round',
        ]);
    }

    #[Test]
    public function owner_can_update_table_with_constructor_position(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $hall = $this->hallForRestaurant($restaurant);
        $table = DiningTable::factory()->create(['hall_id' => $hall->id]);

        $this->actingAs($user)
            ->putJson("/api/v1/dining-tables/{$table->id}", [
                'number' => $table->number,
                'x' => 120.50,
                'y' => 80.25,
                'width' => 80.00,
                'height' => 80.00,
                'rotation' => 45,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.x', 120.5)
            ->assertJsonPath('data.attributes.rotation', 45);
    }

    #[Test]
    public function owner_can_delete_table(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $hall = $this->hallForRestaurant($restaurant);
        $table = DiningTable::factory()->create(['hall_id' => $hall->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/dining-tables/{$table->id}")
            ->assertStatus(204);

        $this->assertModelMissing($table);
    }

    #[Test]
    public function cannot_access_table_from_other_restaurant(): void
    {
        $restaurant1 = Restaurant::factory()->create();
        $restaurant2 = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant1);
        $hall = Hall::factory()->create(['restaurant_id' => $restaurant2->id]);
        $table = DiningTable::factory()->create(['hall_id' => $hall->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/dining-tables/{$table->id}")
            ->assertStatus(403);
    }
}
