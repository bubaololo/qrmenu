<?php

namespace Tests\Feature;

use App\Enums\RestaurantUserRole;
use App\Models\DiningTable;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QrCodeTest extends TestCase
{
    use RefreshDatabase;

    private function asOwnerOf(Restaurant $restaurant): User
    {
        $user = User::factory()->create();
        $restaurant->users()->attach($user, ['role' => RestaurantUserRole::Owner->value]);

        return $user;
    }

    #[Test]
    public function restaurant_qr_returns_png_binary(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);

        $response = $this->actingAs($user)
            ->get("/api/v1/restaurants/{$restaurant->id}/qr")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'image/png');

        $this->assertTrue(str_starts_with($response->getContent(), "\x89PNG\r\n\x1a\n"));
    }

    #[Test]
    public function restaurant_qr_requires_authentication(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->get("/api/v1/restaurants/{$restaurant->id}/qr", ['Accept' => 'application/json'])
            ->assertStatus(401);
    }

    #[Test]
    public function unauthenticated_html_request_returns_401_json_not_redirect(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->get("/api/v1/restaurants/{$restaurant->id}/qr", [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ])
            ->assertStatus(401)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    #[Test]
    public function non_member_cannot_get_restaurant_qr(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['created_by_user_id' => $owner->id]);

        $this->actingAs($other)
            ->get("/api/v1/restaurants/{$restaurant->id}/qr")
            ->assertStatus(403);
    }

    #[Test]
    public function table_qr_returns_png_binary(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $zone = Zone::factory()->create(['restaurant_id' => $restaurant->id]);
        $table = DiningTable::factory()->create(['zone_id' => $zone->id]);

        $response = $this->actingAs($user)
            ->get("/api/v1/dining-tables/{$table->id}/qr")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'image/png');

        $this->assertTrue(str_starts_with($response->getContent(), "\x89PNG\r\n\x1a\n"));
    }

    #[Test]
    public function table_qr_requires_authentication(): void
    {
        $table = DiningTable::factory()->create();

        $this->get("/api/v1/dining-tables/{$table->id}/qr", ['Accept' => 'application/json'])
            ->assertStatus(401);
    }

    #[Test]
    public function non_member_cannot_get_table_qr(): void
    {
        $other = User::factory()->create();
        $restaurant = Restaurant::factory()->create();
        $zone = Zone::factory()->create(['restaurant_id' => $restaurant->id]);
        $table = DiningTable::factory()->create(['zone_id' => $zone->id]);

        $this->actingAs($other)
            ->get("/api/v1/dining-tables/{$table->id}/qr")
            ->assertStatus(403);
    }
}
