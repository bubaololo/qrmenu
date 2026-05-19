<?php

namespace Tests\Feature;

use App\Models\Icon;
use App\Support\FoodIcons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IconSpriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function seedIcon(string $name, ?string $svg = null): Icon
    {
        return Icon::create([
            'name' => $name,
            'svg' => $svg ?? "<symbol id=\"{$name}\" viewBox=\"0 0 24 24\" fill=\"none\"><path d=\"M0 0\"/></symbol>",
        ]);
    }

    #[Test]
    public function test_full_sprite_contains_all_icons_in_db(): void
    {
        $this->seedIcon('pizza');
        $this->seedIcon('sushi');

        $sprite = FoodIcons::sprite();

        $this->assertStringContainsString('<symbol id="pizza"', $sprite);
        $this->assertStringContainsString('<symbol id="sushi"', $sprite);
        $this->assertStringStartsWith('<svg', $sprite);
        $this->assertStringEndsWith('</svg>', $sprite);
    }

    #[Test]
    public function test_full_sprite_returns_empty_string_when_db_is_empty(): void
    {
        $this->assertSame('', FoodIcons::sprite());
    }

    #[Test]
    public function test_sprite_filters_to_requested_names(): void
    {
        $this->seedIcon('pizza');
        $this->seedIcon('sushi');
        $this->seedIcon('burger');

        $sprite = FoodIcons::sprite(['pizza', 'sushi']);

        $this->assertStringContainsString('<symbol id="pizza"', $sprite);
        $this->assertStringContainsString('<symbol id="sushi"', $sprite);
        $this->assertStringNotContainsString('<symbol id="burger"', $sprite);
    }

    #[Test]
    public function test_sprite_silently_drops_unknown_names(): void
    {
        $this->seedIcon('pizza');

        $sprite = FoodIcons::sprite(['pizza', 'nonexistent']);

        $this->assertStringContainsString('<symbol id="pizza"', $sprite);
        $this->assertStringNotContainsString('nonexistent', $sprite);
    }

    #[Test]
    public function test_sprite_returns_empty_when_all_names_unknown(): void
    {
        $this->seedIcon('pizza');

        $this->assertSame('', FoodIcons::sprite(['nonexistent', 'also-missing']));
    }

    #[Test]
    public function test_sprite_dedupes_repeated_names(): void
    {
        $this->seedIcon('pizza');

        $sprite = FoodIcons::sprite(['pizza', 'pizza', 'pizza']);

        $this->assertSame(1, substr_count($sprite, '<symbol id="pizza"'));
    }

    #[Test]
    public function test_observer_flushes_full_sprite_cache_on_icon_save(): void
    {
        $icon = $this->seedIcon('pizza');

        FoodIcons::sprite();
        $this->assertTrue(Cache::has('icon_sprite:full'));

        $icon->update(['svg' => '<symbol id="pizza"><circle/></symbol>']);

        $this->assertFalse(Cache::has('icon_sprite:full'));
    }

    #[Test]
    public function test_observer_flushes_per_symbol_cache_on_icon_save(): void
    {
        $icon = $this->seedIcon('pizza');

        FoodIcons::sprite(['pizza']);
        $this->assertTrue(Cache::has('icon_sprite:symbol:pizza'));

        $icon->update(['svg' => '<symbol id="pizza"><rect/></symbol>']);

        $this->assertFalse(Cache::has('icon_sprite:symbol:pizza'));
    }

    #[Test]
    public function test_observer_flushes_names_list_cache_on_icon_save(): void
    {
        $this->seedIcon('pizza');

        FoodIcons::namesList();
        $this->assertTrue(Cache::has('icon_names:list'));

        $this->seedIcon('sushi');

        $this->assertFalse(Cache::has('icon_names:list'));
    }

    #[Test]
    public function test_observer_flushes_cache_on_icon_delete(): void
    {
        $icon = $this->seedIcon('pizza');

        FoodIcons::sprite();
        $this->assertTrue(Cache::has('icon_sprite:full'));

        $icon->delete();

        $this->assertFalse(Cache::has('icon_sprite:full'));
    }

    #[Test]
    public function test_names_list_returns_sorted_comma_separated(): void
    {
        $this->seedIcon('sushi');
        $this->seedIcon('burger');
        $this->seedIcon('pizza');

        $this->assertSame('burger, pizza, sushi', FoodIcons::namesList());
    }

    #[Test]
    public function test_names_list_returns_empty_string_when_db_empty(): void
    {
        $this->assertSame('', FoodIcons::namesList());
    }

    #[Test]
    public function test_menu_sprite_route_returns_svg_with_correct_headers(): void
    {
        $this->seedIcon('pizza');

        $response = $this->get('/menu-sprite.svg');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8');
        $response->assertHeader('Cache-Control', 'max-age=3600, must-revalidate, public');
        $this->assertStringContainsString('<symbol id="pizza"', $response->getContent());
    }

    #[Test]
    public function test_menu_sprite_route_returns_304_when_etag_matches(): void
    {
        $this->seedIcon('pizza');

        $first = $this->get('/menu-sprite.svg');
        $etag = $first->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $second = $this->withHeader('If-None-Match', $etag)->get('/menu-sprite.svg');

        $second->assertStatus(304);
    }

    #[Test]
    public function test_icons_sync_command_populates_icons_table_from_disk(): void
    {
        $this->assertSame(0, Icon::count());

        $this->artisan('icons:sync')->assertSuccessful();

        $count = Icon::whereNotNull('svg')->where('svg', '!=', '')->count();
        $this->assertGreaterThan(40, $count, 'icons:sync should populate at least 40 icons');
        $this->assertTrue(Icon::where('name', 'pizza')->exists());
    }

    #[Test]
    public function test_icons_sync_is_idempotent(): void
    {
        $this->artisan('icons:sync')->assertSuccessful();
        $firstCount = Icon::count();

        $this->artisan('icons:sync')->assertSuccessful();

        $this->assertSame($firstCount, Icon::count());
    }
}
