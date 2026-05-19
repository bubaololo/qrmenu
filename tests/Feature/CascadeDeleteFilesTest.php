<?php

namespace Tests\Feature;

use App\Jobs\DeleteImageFilesJob;
use App\Models\Bill;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\MenuAnalysis;
use App\Models\MenuItem;
use App\Models\MenuOptionGroup;
use App\Models\MenuOptionGroupOption;
use App\Models\MenuSection;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\Translation;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CascadeDeleteFilesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_delete_restaurant_dispatches_job_with_all_descendant_file_paths(): void
    {
        Queue::fake();

        $restaurant = Restaurant::factory()->create([
            'image' => 'restaurants/r1.webp',
            'logo' => 'logos/l1.webp',
        ]);

        $zoneA = Zone::factory()->create([
            'restaurant_id' => $restaurant->id,
            'image' => 'zones/z1.webp',
        ]);
        Zone::factory()->create([
            'restaurant_id' => $restaurant->id,
            'image' => null,
        ]);

        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        MenuItem::factory()->create([
            'section_id' => $section->id,
            'image' => 'menu-items/i1.webp',
        ]);
        MenuItem::factory()->create([
            'section_id' => $section->id,
            'image' => 'menu-items/i2.webp',
        ]);
        MenuItem::factory()->create([
            'section_id' => $section->id,
            'image' => null,
        ]);

        MenuAnalysis::factory()->create([
            'restaurant_id' => $restaurant->id,
            'image_paths' => ['originals/a-main.webp'],
            'original_image_paths' => ['originals/a-raw.jpg'],
            'image_disk' => 'public',
        ]);

        $restaurant->delete();

        Queue::assertPushed(DeleteImageFilesJob::class, function (DeleteImageFilesJob $job) {
            $public = $job->pathsByDisk['public'] ?? [];
            $local = $job->pathsByDisk['local'] ?? [];

            return in_array('restaurants/r1.webp', $public, true)
                && in_array('restaurants/r1_thumb.webp', $public, true)
                && in_array('logos/l1.webp', $public, true)
                && in_array('logos/l1_thumb.webp', $public, true)
                && in_array('zones/z1.webp', $public, true)
                && in_array('zones/z1_thumb.webp', $public, true)
                && in_array('menu-items/i1.webp', $public, true)
                && in_array('menu-items/i1_thumb.webp', $public, true)
                && in_array('menu-items/i2.webp', $public, true)
                && in_array('menu-items/i2_thumb.webp', $public, true)
                && in_array('originals/a-main.webp', $public, true)
                && in_array('originals/a-raw.jpg', $local, true);
        });
    }

    #[Test]
    public function test_delete_restaurant_cascades_full_chain_in_db(): void
    {
        Queue::fake();

        $restaurant = Restaurant::factory()->create();
        $zone = Zone::factory()->create(['restaurant_id' => $restaurant->id]);
        $table = DiningTable::factory()->create(['zone_id' => $zone->id]);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);
        $group = MenuOptionGroup::factory()->create(['section_id' => $section->id]);
        $option = MenuOptionGroupOption::factory()->create(['group_id' => $group->id]);

        $item->setTranslation('name', 'en', 'Pho', true);
        $group->setTranslation('name', 'en', 'Size', true);
        $option->setTranslation('name', 'en', 'Large', true);
        $zone->setTranslation('name', 'en', 'Patio', true);

        $bill = Bill::factory()->create(['dining_table_id' => $table->id]);
        $order = Order::factory()->create(['bill_id' => $bill->id]);
        OrderItem::factory()->create(['order_id' => $order->id, 'menu_item_id' => $item->id]);

        $analysis = MenuAnalysis::factory()->create(['restaurant_id' => $restaurant->id]);

        $restaurant->delete();

        $this->assertDatabaseMissing('restaurants', ['id' => $restaurant->id]);
        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
        $this->assertDatabaseMissing('menu_sections', ['id' => $section->id]);
        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
        $this->assertDatabaseMissing('menu_option_groups', ['id' => $group->id]);
        $this->assertDatabaseMissing('menu_option_group_options', ['id' => $option->id]);
        $this->assertDatabaseMissing('zones', ['id' => $zone->id]);
        $this->assertDatabaseMissing('dining_tables', ['id' => $table->id]);
        $this->assertDatabaseMissing('bills', ['id' => $bill->id]);
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('menu_analyses', ['id' => $analysis->id]);
        $this->assertSame(0, Translation::count());
    }

    #[Test]
    public function test_delete_menu_dispatches_job_with_only_item_files(): void
    {
        Queue::fake();

        $restaurant = Restaurant::factory()->create();
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        MenuItem::factory()->create([
            'section_id' => $section->id,
            'image' => 'menu-items/m1.webp',
        ]);

        $menu->delete();

        Queue::assertPushed(DeleteImageFilesJob::class, function (DeleteImageFilesJob $job) {
            $public = $job->pathsByDisk['public'] ?? [];

            return in_array('menu-items/m1.webp', $public, true)
                && in_array('menu-items/m1_thumb.webp', $public, true);
        });
    }

    #[Test]
    public function test_delete_menu_without_item_images_does_not_dispatch_job(): void
    {
        Queue::fake();

        $restaurant = Restaurant::factory()->create();
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        MenuItem::factory()->create(['section_id' => $section->id, 'image' => null]);

        $menu->delete();

        Queue::assertNotPushed(DeleteImageFilesJob::class);
    }

    #[Test]
    public function test_delete_section_dispatches_job_with_item_files(): void
    {
        Queue::fake();

        $restaurant = Restaurant::factory()->create();
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        MenuItem::factory()->create([
            'section_id' => $section->id,
            'image' => 'menu-items/s1.webp',
        ]);

        $section->delete();

        Queue::assertPushed(DeleteImageFilesJob::class, function (DeleteImageFilesJob $job) {
            $public = $job->pathsByDisk['public'] ?? [];

            return in_array('menu-items/s1.webp', $public, true)
                && in_array('menu-items/s1_thumb.webp', $public, true);
        });
    }

    #[Test]
    public function test_delete_menu_item_directly_dispatches_job_with_own_files(): void
    {
        Queue::fake();

        $restaurant = Restaurant::factory()->create();
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create([
            'section_id' => $section->id,
            'image' => 'menu-items/x.webp',
        ]);

        $item->delete();

        Queue::assertPushed(DeleteImageFilesJob::class, function (DeleteImageFilesJob $job) {
            return ($job->pathsByDisk['public'] ?? []) === [
                'menu-items/x.webp',
                'menu-items/x_thumb.webp',
            ];
        });
    }

    #[Test]
    public function test_delete_menu_item_without_image_does_not_dispatch(): void
    {
        Queue::fake();

        $restaurant = Restaurant::factory()->create();
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id, 'image' => null]);

        $item->delete();

        Queue::assertNotPushed(DeleteImageFilesJob::class);
    }

    #[Test]
    public function test_delete_zone_directly_dispatches_job_with_own_files(): void
    {
        Queue::fake();

        $restaurant = Restaurant::factory()->create();
        $zone = Zone::factory()->create([
            'restaurant_id' => $restaurant->id,
            'image' => 'zones/z.webp',
        ]);

        $zone->delete();

        Queue::assertPushed(DeleteImageFilesJob::class, function (DeleteImageFilesJob $job) {
            return ($job->pathsByDisk['public'] ?? []) === [
                'zones/z.webp',
                'zones/z_thumb.webp',
            ];
        });
    }

    #[Test]
    public function test_delete_menu_analysis_directly_dispatches_job_with_image_paths(): void
    {
        Queue::fake();

        $restaurant = Restaurant::factory()->create();
        $analysis = MenuAnalysis::factory()->create([
            'restaurant_id' => $restaurant->id,
            'image_paths' => ['restaurants/a-main.webp', 'restaurants/a-main2.webp'],
            'original_image_paths' => ['originals/a-raw.jpg'],
            'image_disk' => 'public',
        ]);

        $analysis->delete();

        Queue::assertPushed(DeleteImageFilesJob::class, function (DeleteImageFilesJob $job) {
            $public = $job->pathsByDisk['public'] ?? [];
            $local = $job->pathsByDisk['local'] ?? [];

            return $public === ['restaurants/a-main.webp', 'restaurants/a-main2.webp']
                && $local === ['originals/a-raw.jpg'];
        });
    }

    #[Test]
    public function test_delete_restaurant_without_any_files_does_not_dispatch_job(): void
    {
        Queue::fake();

        $restaurant = Restaurant::factory()->create(['image' => null, 'logo' => null]);
        Zone::factory()->create(['restaurant_id' => $restaurant->id, 'image' => null]);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        MenuItem::factory()->create(['section_id' => $section->id, 'image' => null]);

        $restaurant->delete();

        Queue::assertNotPushed(DeleteImageFilesJob::class);
    }
}
