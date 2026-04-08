<?php

namespace Tests\Feature;

use App\Actions\SaveMenuAnalysisAction;
use App\Enums\RestaurantUserRole;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\MenuJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RestaurantTest extends TestCase
{
    use RefreshDatabase;

    private function asOwnerOf(Restaurant $restaurant): User
    {
        $user = User::factory()->create();
        $restaurant->users()->attach($user, ['role' => RestaurantUserRole::Owner->value]);

        return $user;
    }

    #[Test]
    public function test_unauthenticated_cannot_list_restaurants(): void
    {
        $this->getJson('/api/v1/restaurants')->assertStatus(401);
    }

    #[Test]
    public function test_authenticated_user_sees_only_own_restaurants(): void
    {
        $restaurant1 = Restaurant::factory()->create();
        $restaurant2 = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant1);
        $this->asOwnerOf($restaurant2); // different owner

        $this->actingAs($user)
            ->getJson('/api/v1/restaurants')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'restaurants')
            ->assertJsonPath('data.0.id', (string) $restaurant1->id);
    }

    #[Test]
    public function test_store_creates_restaurant_and_attaches_owner(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/restaurants')
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'restaurants');

        $restaurantId = $response->json('data.id');

        $this->assertDatabaseHas('restaurants', [
            'id' => $restaurantId,
            'created_by_user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('restaurant_users', [
            'restaurant_id' => $restaurantId,
            'user_id' => $user->id,
            'role' => RestaurantUserRole::Owner->value,
        ]);
    }

    #[Test]
    public function test_show_returns_restaurant_for_owner(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);

        $this->actingAs($user)
            ->getJson("/api/v1/restaurants/{$restaurant->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.type', 'restaurants')
            ->assertJsonPath('data.id', (string) $restaurant->id)
            ->assertJsonStructure(['data' => ['type', 'id', 'attributes']]);
    }

    #[Test]
    public function test_show_returns_403_for_non_owner(): void
    {
        $restaurant = Restaurant::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson("/api/v1/restaurants/{$restaurant->id}")
            ->assertStatus(403);
    }

    #[Test]
    public function test_active_menus_returns_sections_and_items(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);

        $raw = file_get_contents(base_path('tests/llm_responce.json'));
        $menuData = MenuJson::decodeMenuFromLlmText($raw);
        $menu = (new SaveMenuAnalysisAction)->handle($menuData, $restaurant->id, 1);
        $menu->update(['is_active' => true]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/restaurants/active-menus')
            ->assertStatus(200);

        $response->assertJsonPath('data.0.restaurant_id', $restaurant->id);
        $this->assertCount(8, $response->json('data.0.sections'));
    }

    #[Test]
    public function test_active_menus_omits_restaurants_without_active_menu(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);

        $this->actingAs($user)
            ->getJson('/api/v1/restaurants/active-menus')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
