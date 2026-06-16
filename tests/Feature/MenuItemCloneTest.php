<?php

namespace Tests\Feature;

use App\Enums\RestaurantUserRole;
use App\Jobs\TranslateEntityJob;
use App\Models\Menu;
use App\Models\MenuAddon;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\MenuVariation;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuItemCloneTest extends TestCase
{
    use RefreshDatabase;

    private function asOwnerOf(Restaurant $restaurant): User
    {
        $user = User::factory()->create();
        $restaurant->users()->attach($user, ['role' => RestaurantUserRole::Owner->value]);

        return $user;
    }

    #[Test]
    public function test_clone_duplicates_item_and_reuses_variation_and_addon_pivots(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'en']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);
        $item->setTranslation('name', 'en', 'Burger', isInitial: true);
        $variation = MenuVariation::factory()->create(['menu_id' => $menu->id]);
        $addon = MenuAddon::factory()->create(['menu_id' => $menu->id]);
        $item->variations()->attach($variation->id);
        $item->addons()->attach($addon->id);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/menu-items/{$item->id}/clone")
            ->assertStatus(201);

        $cloneId = (int) $response->json('data.id');
        $this->assertNotSame($item->id, $cloneId);

        $clone = MenuItem::findOrFail($cloneId);
        $this->assertSame('Burger (копия)', $clone->translate('name', 'en'));
        // The same shared variation/add-on are reused via pivot (not duplicated).
        $this->assertSame([$variation->id], $clone->variations()->pluck('menu_variations.id')->all());
        $this->assertSame([$addon->id], $clone->addons()->pluck('menu_addons.id')->all());
        $this->assertSame(1, MenuVariation::count());
        $this->assertSame(1, MenuAddon::count());
    }

    #[Test]
    public function test_clone_does_not_trigger_retranslation(): void
    {
        Bus::fake();

        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'en']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);
        $item->setTranslation('name', 'en', 'Burger', isInitial: true);
        $item->setTranslation('name', 'ru', 'Бургер', isInitial: false);

        $this->actingAs($user)
            ->postJson("/api/v1/menu-items/{$item->id}/clone")
            ->assertStatus(201);

        // The clone already carries every locale's translation, so re-translation
        // must NOT be dispatched (would be redundant and costly on production).
        Bus::assertNotDispatched(TranslateEntityJob::class);
    }
}
