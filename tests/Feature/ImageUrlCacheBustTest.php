<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * URLs returned by the *_url accessors carry a `?v={updated_at}` suffix so
 * nginx can serve `/storage/*` with `Cache-Control: immutable` without
 * pinning clients to a stale image after an in-place re-upload (a real
 * concern for the deterministic `item_{id}.webp` path written by
 * CropMenuItemImagesJob on re-analysis).
 */
class ImageUrlCacheBustTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function menu_item_urls_include_updated_at_version(): void
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['created_by_user_id' => $user->id]);
        $menu = Menu::factory()->for($restaurant)->create();
        $section = MenuSection::factory()->for($menu)->create();
        $item = MenuItem::factory()->for($section, 'section')->create([
            'image' => 'menu-items/9/item_42.webp',
        ]);

        $version = $item->updated_at->timestamp;
        $this->assertStringEndsWith('item_42.webp?v='.$version, $item->image_url);
        $this->assertStringEndsWith('item_42_thumb.webp?v='.$version, $item->thumb_url);
    }

    #[Test]
    public function reuploading_same_path_changes_cache_bust_version(): void
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['created_by_user_id' => $user->id]);
        $menu = Menu::factory()->for($restaurant)->create();
        $section = MenuSection::factory()->for($menu)->create();
        $item = MenuItem::factory()->for($section, 'section')->create([
            'image' => 'menu-items/9/item_42.webp',
        ]);

        $before = $item->image_url;

        $this->travel(2)->seconds();
        $item->touch();

        $this->assertNotSame($before, $item->fresh()->image_url);
    }

    #[Test]
    public function restaurant_image_and_logo_urls_include_version(): void
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create([
            'created_by_user_id' => $user->id,
            'image' => 'restaurants/hero.webp',
            'logo' => 'logos/brand.webp',
        ]);

        $version = $restaurant->updated_at->timestamp;
        $this->assertStringEndsWith('hero.webp?v='.$version, $restaurant->image_url);
        $this->assertStringEndsWith('hero_thumb.webp?v='.$version, $restaurant->thumb_url);
        $this->assertStringEndsWith('brand.webp?v='.$version, $restaurant->logo_url);
        $this->assertStringEndsWith('brand_thumb.webp?v='.$version, $restaurant->logo_thumb_url);
    }

    #[Test]
    public function zone_image_url_includes_version(): void
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['created_by_user_id' => $user->id]);
        $zone = Zone::factory()->create([
            'restaurant_id' => $restaurant->id,
            'image' => 'zones/main.webp',
        ]);

        $version = $zone->updated_at->timestamp;
        $this->assertStringEndsWith('main.webp?v='.$version, $zone->image_url);
        $this->assertStringEndsWith('main_thumb.webp?v='.$version, $zone->thumb_url);
    }

    #[Test]
    public function url_accessors_return_null_when_no_image(): void
    {
        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create([
            'created_by_user_id' => $user->id,
            'image' => null,
            'logo' => null,
        ]);

        $this->assertNull($restaurant->image_url);
        $this->assertNull($restaurant->thumb_url);
        $this->assertNull($restaurant->logo_url);
        $this->assertNull($restaurant->logo_thumb_url);
    }
}
