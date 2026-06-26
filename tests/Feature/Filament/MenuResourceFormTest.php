<?php

namespace Tests\Feature\Filament;

use App\Enums\RestaurantUserRole;
use App\Filament\Resources\Menus\Pages\EditMenu;
use App\Models\Menu;
use App\Models\Restaurant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuResourceFormTest extends TestCase
{
    use RefreshDatabase;

    private string $adminEmail = 'panel-admin@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        // The admin panel gates access on isAdmin() (which reads ADMIN_EMAILS via env)
        // and the MenuPolicy requires ownership of the menu's restaurant to edit.
        putenv("ADMIN_EMAILS={$this->adminEmail}");
        $_ENV['ADMIN_EMAILS'] = $this->adminEmail;
        $_SERVER['ADMIN_EMAILS'] = $this->adminEmail;

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function actingAsAdminOwnerOf(Menu $menu): User
    {
        $user = User::factory()->create(['email' => $this->adminEmail]);
        $menu->restaurant->users()->attach($user, ['role' => RestaurantUserRole::Owner->value]);
        $this->actingAs($user);

        return $user;
    }

    #[Test]
    public function test_menu_can_be_rebound_to_a_restaurant_without_a_menu(): void
    {
        $menu = Menu::factory()->create();
        $this->actingAsAdminOwnerOf($menu);
        $freeRestaurant = Restaurant::factory()->create();

        Livewire::test(EditMenu::class, ['record' => $menu->getRouteKey()])
            ->fillForm(['restaurant_id' => $freeRestaurant->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($freeRestaurant->id, $menu->refresh()->restaurant_id);
    }

    #[Test]
    public function test_rebinding_to_a_restaurant_that_already_has_a_menu_is_rejected(): void
    {
        $menu = Menu::factory()->create();
        $this->actingAsAdminOwnerOf($menu);
        $originalRestaurantId = $menu->restaurant_id;

        $taken = Restaurant::factory()->create();
        Menu::factory()->create(['restaurant_id' => $taken->id]);

        Livewire::test(EditMenu::class, ['record' => $menu->getRouteKey()])
            ->fillForm(['restaurant_id' => $taken->id])
            ->call('save')
            ->assertHasFormErrors(['restaurant_id' => 'unique']);

        $this->assertSame($originalRestaurantId, $menu->refresh()->restaurant_id);
    }

    #[Test]
    public function test_saving_menu_in_place_keeps_its_own_restaurant(): void
    {
        $menu = Menu::factory()->create();
        $this->actingAsAdminOwnerOf($menu);

        Livewire::test(EditMenu::class, ['record' => $menu->getRouteKey()])
            ->fillForm(['restaurant_id' => $menu->restaurant_id])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    #[Test]
    public function test_edit_form_saves_source_and_clone_fields(): void
    {
        $menu = Menu::factory()->create();
        $this->actingAsAdminOwnerOf($menu);
        $clonedFrom = Menu::factory()->create();

        Livewire::test(EditMenu::class, ['record' => $menu->getRouteKey()])
            ->fillForm([
                'source_locale' => 'en',
                'source_images_count' => 4,
                'created_from_menu_id' => $clonedFrom->id,
                'detected_date' => '2026-06-01',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $menu->refresh();

        $this->assertSame('en', $menu->source_locale);
        $this->assertSame(4, $menu->source_images_count);
        $this->assertSame($clonedFrom->id, $menu->created_from_menu_id);
        $this->assertSame('2026-06-01', $menu->detected_date->format('Y-m-d'));
    }
}
