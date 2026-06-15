<?php

namespace Tests\Feature;

use App\Events\RealtimeEvent;
use App\Jobs\ProcessImageJob;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Observers\MenuItemObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RealtimeBroadcastTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creating_a_menu_item_broadcasts_to_the_restaurant_channel(): void
    {
        $restaurant = Restaurant::factory()->create();
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);

        Event::fake([RealtimeEvent::class]);

        $item = MenuItem::factory()->create(['section_id' => $section->id]);

        Event::assertDispatched(RealtimeEvent::class, function (RealtimeEvent $e) use ($restaurant, $item): bool {
            return $e->topic === "restaurant.{$restaurant->id}"
                && $e->event === 'menu-item.created'
                && $e->payload['item_id'] === $item->id
                && $e->payload['menu_id'] === $item->section->menu_id;
        });
    }

    #[Test]
    public function muted_block_suppresses_menu_item_broadcasts(): void
    {
        $restaurant = Restaurant::factory()->create();
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);

        Event::fake([RealtimeEvent::class]);

        MenuItemObserver::muted(function () use ($section): void {
            MenuItem::factory()->create(['section_id' => $section->id]);
        });

        Event::assertNotDispatched(RealtimeEvent::class);
    }

    #[Test]
    public function failed_image_job_broadcasts_image_failed(): void
    {
        Event::fake([RealtimeEvent::class]);

        $job = new ProcessImageJob(
            modelClass: MenuItem::class,
            modelId: 99,
            restaurantId: 7,
            tempPath: 'originals/missing.webp',
            targetDir: 'menu-items/1',
            baseName: 'abc',
        );

        $job->failed(new \RuntimeException('boom'));

        Event::assertDispatched(RealtimeEvent::class, function (RealtimeEvent $e): bool {
            return $e->topic === 'restaurant.7'
                && $e->event === 'image.failed'
                && $e->payload['error'] === 'boom'
                && $e->payload['model_id'] === 99;
        });
    }
}
