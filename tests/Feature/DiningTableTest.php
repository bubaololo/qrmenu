<?php

namespace Tests\Feature;

use App\Enums\DiningTableShape;
use App\Enums\RestaurantUserRole;
use App\Models\DiningTable;
use App\Models\Restaurant;
use App\Models\TableShape;
use App\Models\User;
use App\Models\Zone;
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

    private function zoneForRestaurant(Restaurant $restaurant): Zone
    {
        return Zone::factory()->create(['restaurant_id' => $restaurant->id]);
    }

    #[Test]
    public function unauthenticated_cannot_list_tables(): void
    {
        $zone = Zone::factory()->create();
        $this->getJson("/api/v1/zones/{$zone->id}/tables")->assertStatus(401);
    }

    #[Test]
    public function owner_can_list_tables(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $zone = $this->zoneForRestaurant($restaurant);
        DiningTable::factory()->count(4)->create(['zone_id' => $zone->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/zones/{$zone->id}/tables")
            ->assertStatus(200)
            ->assertJsonCount(4, 'data');
    }

    #[Test]
    public function table_resource_exposes_public_menu_url(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $zone = $this->zoneForRestaurant($restaurant);
        $table = DiningTable::factory()->create(['zone_id' => $zone->id]);

        $expected = config('app.url')."/{$restaurant->uniqid}/t/{$table->uniqid}";

        $this->actingAs($user)
            ->getJson("/api/v1/dining-tables/{$table->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.menu_url', $expected);
    }

    #[Test]
    public function owner_can_create_table(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $zone = $this->zoneForRestaurant($restaurant);

        $this->actingAs($user)
            ->postJson("/api/v1/zones/{$zone->id}/tables", [
                'number' => 5,
                'capacity' => 4,
                'shape' => DiningTableShape::Round->value,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'dining_tables')
            ->assertJsonPath('data.attributes.number', 5)
            ->assertJsonPath('data.attributes.shape', 'round');

        $this->assertDatabaseHas('dining_tables', [
            'zone_id' => $zone->id,
            'number' => 5,
            'capacity' => 4,
            'table_shape_id' => TableShape::where('name', 'round')->value('id'),
        ]);
    }

    #[Test]
    public function owner_can_update_table_with_constructor_position(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $zone = $this->zoneForRestaurant($restaurant);
        $table = DiningTable::factory()->create(['zone_id' => $zone->id]);

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
        $zone = $this->zoneForRestaurant($restaurant);
        $table = DiningTable::factory()->create(['zone_id' => $zone->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/dining-tables/{$table->id}")
            ->assertStatus(204);

        $this->assertModelMissing($table);
    }

    #[Test]
    public function new_table_gets_auto_generated_uniqid(): void
    {
        $zone = Zone::factory()->create();
        $table = DiningTable::factory()->create(['zone_id' => $zone->id]);

        $this->assertNotEmpty($table->uniqid);
        $this->assertDoesNotMatchRegularExpression('/\./', $table->uniqid);

        $another = DiningTable::factory()->create(['zone_id' => $zone->id]);
        $this->assertNotSame($table->uniqid, $another->uniqid);
    }

    #[Test]
    public function explicit_uniqid_is_preserved_on_create(): void
    {
        $zone = Zone::factory()->create();
        $table = DiningTable::factory()->create([
            'zone_id' => $zone->id,
            'uniqid' => 'custom-unique-value',
        ]);

        $this->assertSame('custom-unique-value', $table->fresh()->uniqid);
    }

    #[Test]
    public function cannot_access_table_from_other_restaurant(): void
    {
        $restaurant1 = Restaurant::factory()->create();
        $restaurant2 = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant1);
        $zone = Zone::factory()->create(['restaurant_id' => $restaurant2->id]);
        $table = DiningTable::factory()->create(['zone_id' => $zone->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/dining-tables/{$table->id}")
            ->assertStatus(403);
    }
}
