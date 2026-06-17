<?php

namespace Tests\Feature;

use App\Enums\RestaurantUserRole;
use App\Jobs\TranslateEntityJob;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\ModifierGroup;
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
    public function test_clone_duplicates_item_and_reuses_modifier_group_pivots(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'en']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);
        $item->setTranslation('name', 'en', 'Burger', isInitial: true);
        $sizeGroup = ModifierGroup::factory()->variation()->create(['menu_id' => $menu->id]);
        $extrasGroup = ModifierGroup::factory()->create(['menu_id' => $menu->id]);
        $item->modifierGroups()->attach([$sizeGroup->id, $extrasGroup->id]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/menu-items/{$item->id}/clone")
            ->assertStatus(201);

        $cloneId = (int) $response->json('data.id');
        $this->assertNotSame($item->id, $cloneId);

        $clone = MenuItem::findOrFail($cloneId);
        $this->assertSame('Burger (copy)', $clone->translate('name', 'en'));
        // The same shared modifier groups are reused via pivot (not duplicated).
        $this->assertEqualsCanonicalizing(
            [$sizeGroup->id, $extrasGroup->id],
            $clone->modifierGroups()->pluck('modifier_groups.id')->all(),
        );
        $this->assertSame(2, ModifierGroup::count());
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
