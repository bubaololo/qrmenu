<?php

namespace Tests\Feature\Filament;

use App\Enums\RestaurantUserRole;
use App\Filament\Resources\Restaurants\Pages\EditRestaurant;
use App\Models\Restaurant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RestaurantResourceFormTest extends TestCase
{
    use RefreshDatabase;

    private string $adminEmail = 'panel-admin@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        // The admin panel gates access on isAdmin() (which reads ADMIN_EMAILS via env)
        // and the RestaurantPolicy requires ownership to edit.
        putenv("ADMIN_EMAILS={$this->adminEmail}");
        $_ENV['ADMIN_EMAILS'] = $this->adminEmail;
        $_SERVER['ADMIN_EMAILS'] = $this->adminEmail;

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function actingAsAdminOwnerOf(Restaurant $restaurant): User
    {
        $user = User::factory()->create(['email' => $this->adminEmail]);
        $restaurant->users()->attach($user, ['role' => RestaurantUserRole::Owner->value]);
        $this->actingAs($user);

        return $user;
    }

    #[Test]
    public function test_edit_form_saves_all_restaurant_fields(): void
    {
        $restaurant = Restaurant::factory()->create();
        $this->actingAsAdminOwnerOf($restaurant);
        $newOwner = User::factory()->create();

        Livewire::test(EditRestaurant::class, ['record' => $restaurant->getRouteKey()])
            ->fillForm([
                'name' => 'Edited Bistro',
                'address' => '12 Le Loi Street',
                'created_by_user_id' => $newOwner->id,
                'city' => 'Hanoi',
                'country' => 'Vietnam',
                'phone' => '+84 24 1234 5678',
                'google_maps_url' => 'https://maps.google.com/?q=bistro',
                'currency' => 'VND',
                'primary_language' => 'vi',
                'max_languages' => 3,
                'coordinates' => ['lat' => 21.0285, 'lng' => 105.8542],
                'opening_hours' => [
                    'is_24_7' => false,
                    'raw_text' => 'Mon-Fri 09:00-17:00',
                    'periods' => [
                        ['days' => ['mon', 'tue', 'wed', 'thu', 'fri'], 'open' => '09:00', 'close' => '17:00'],
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $restaurant->refresh();

        $this->assertSame('Edited Bistro', $restaurant->name);
        $this->assertSame('12 Le Loi Street', $restaurant->address);
        $this->assertSame($newOwner->id, $restaurant->created_by_user_id);
        $this->assertSame('Hanoi', $restaurant->city);
        $this->assertSame('Vietnam', $restaurant->country);
        $this->assertSame('+84 24 1234 5678', $restaurant->phone);
        $this->assertSame('https://maps.google.com/?q=bistro', $restaurant->google_maps_url);
        $this->assertSame('VND', $restaurant->currency);
        $this->assertSame('vi', $restaurant->primary_language);
        $this->assertSame(3, $restaurant->max_languages);
        $this->assertEqualsWithDelta(21.0285, $restaurant->coordinates['lat'], 0.0001);
        $this->assertEqualsWithDelta(105.8542, $restaurant->coordinates['lng'], 0.0001);
        $this->assertFalse($restaurant->opening_hours['is_24_7']);
        $this->assertSame('Mon-Fri 09:00-17:00', $restaurant->opening_hours['raw_text']);
        $this->assertSame(['mon', 'tue', 'wed', 'thu', 'fri'], $restaurant->opening_hours['periods'][0]['days']);
        $this->assertSame('09:00', $restaurant->opening_hours['periods'][0]['open']);
    }

    #[Test]
    public function test_blank_coordinates_are_stored_as_null(): void
    {
        $restaurant = Restaurant::factory()->create();
        $this->actingAsAdminOwnerOf($restaurant);

        Livewire::test(EditRestaurant::class, ['record' => $restaurant->getRouteKey()])
            ->fillForm([
                'coordinates' => ['lat' => null, 'lng' => null],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($restaurant->refresh()->coordinates);
    }
}
