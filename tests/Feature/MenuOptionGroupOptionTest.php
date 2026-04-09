<?php

namespace Tests\Feature;

use App\Enums\RestaurantUserRole;
use App\Models\Menu;
use App\Models\MenuOptionGroup;
use App\Models\MenuOptionGroupOption;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuOptionGroupOptionTest extends TestCase
{
    use RefreshDatabase;

    private function asOwnerOf(Restaurant $restaurant): User
    {
        $user = User::factory()->create();
        $restaurant->users()->attach($user, ['role' => RestaurantUserRole::Owner->value]);

        return $user;
    }

    #[Test]
    public function test_owner_can_list_options(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $group = MenuOptionGroup::factory()->create(['section_id' => $section->id]);
        MenuOptionGroupOption::factory()->count(3)->create(['group_id' => $group->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/menu-option-groups/{$group->id}/options")
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function test_non_member_cannot_list_options(): void
    {
        $section = MenuSection::factory()->create();
        $group = MenuOptionGroup::factory()->create(['section_id' => $section->id]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson("/api/v1/menu-option-groups/{$group->id}/options")
            ->assertStatus(403);
    }

    #[Test]
    public function test_store_creates_option_with_name(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $group = MenuOptionGroup::factory()->create(['section_id' => $section->id]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/menu-option-groups/{$group->id}/options", [
                'name' => 'Extra Spicy',
                'price_adjust' => '0.50',
                'is_default' => false,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.attributes.name', 'Extra Spicy')
            ->assertJsonPath('data.attributes.price_adjust', '0.50');

        $optionId = $response->json('data.id');
        $this->assertDatabaseHas('translations', [
            'translatable_type' => MenuOptionGroupOption::class,
            'translatable_id' => $optionId,
            'field' => 'name',
            'value' => 'Extra Spicy',
        ]);
    }

    #[Test]
    public function test_show_returns_option(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $group = MenuOptionGroup::factory()->create(['section_id' => $section->id]);
        $option = MenuOptionGroupOption::factory()->create(['group_id' => $group->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/menu-option-group-options/{$option->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', (string) $option->id);
    }

    #[Test]
    public function test_update_modifies_option(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $group = MenuOptionGroup::factory()->create(['section_id' => $section->id]);
        $option = MenuOptionGroupOption::factory()->create(['group_id' => $group->id, 'is_default' => false]);

        $this->actingAs($user)
            ->putJson("/api/v1/menu-option-group-options/{$option->id}", ['is_default' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.is_default', true);

        $this->assertDatabaseHas('menu_option_group_options', ['id' => $option->id, 'is_default' => true]);
    }

    #[Test]
    public function test_destroy_deletes_option(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $group = MenuOptionGroup::factory()->create(['section_id' => $section->id]);
        $option = MenuOptionGroupOption::factory()->create(['group_id' => $group->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/menu-option-group-options/{$option->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('menu_option_group_options', ['id' => $option->id]);
    }

    #[Test]
    public function test_non_owner_gets_403_on_store(): void
    {
        $section = MenuSection::factory()->create();
        $group = MenuOptionGroup::factory()->create(['section_id' => $section->id]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->postJson("/api/v1/menu-option-groups/{$group->id}/options", ['name' => 'Test'])
            ->assertStatus(403);
    }
}
