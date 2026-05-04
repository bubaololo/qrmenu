<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuOptionGroup;
use App\Models\MenuOptionGroupOption;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Models\Translation;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CascadeDeleteTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private Menu $menu;

    /** @var array<int, MenuSection> */
    private array $sections = [];

    /** @var array<int, MenuItem> */
    private array $items = [];

    /** @var array<int, MenuOptionGroup> */
    private array $groups = [];

    /** @var array<int, MenuOptionGroupOption> */
    private array $options = [];

    /** @var array<int, Zone> */
    private array $zones = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create(['primary_language' => 'en']);
        $this->restaurant->setTranslation('name', 'en', 'Test Resto', true);
        $this->restaurant->setTranslation('name', 'fr', 'Resto Test');

        foreach (range(1, 2) as $z) {
            $zone = Zone::factory()->create(['restaurant_id' => $this->restaurant->id]);
            $zone->setTranslation('name', 'en', "Zone $z", true);
            $this->zones[] = $zone;
        }

        $this->menu = Menu::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'source_locale' => 'en',
        ]);

        foreach (range(1, 2) as $s) {
            $section = MenuSection::factory()->create(['menu_id' => $this->menu->id]);
            $section->setTranslation('name', 'en', "Section $s", true);
            $section->setTranslation('name', 'fr', "Section $s FR");
            $this->sections[] = $section;

            foreach (range(1, 5) as $i) {
                $item = MenuItem::factory()->create(['section_id' => $section->id]);
                $item->setTranslation('name', 'en', "Item $s.$i", true);
                $item->setTranslation('description', 'en', "Desc $s.$i", true);
                $this->items[] = $item;
            }

            foreach (range(1, 3) as $g) {
                $group = MenuOptionGroup::factory()->create(['section_id' => $section->id]);
                $group->setTranslation('name', 'en', "Group $s.$g", true);
                $this->groups[] = $group;

                foreach (range(1, 2) as $o) {
                    $option = MenuOptionGroupOption::factory()->create(['group_id' => $group->id]);
                    $option->setTranslation('name', 'en', "Opt $s.$g.$o", true);
                    $this->options[] = $option;
                }
            }
        }
    }

    /** @param  iterable<int, Model>  $models */
    private function countTranslationsFor(string $class, iterable $models): int
    {
        return Translation::where('translatable_type', $class)
            ->whereIn('translatable_id', collect($models)->pluck('id'))
            ->count();
    }

    #[Test]
    public function test_delete_restaurant_cleans_all_descendant_translations(): void
    {
        $this->assertGreaterThan(0, Translation::count());

        $this->restaurant->delete();

        $this->assertSame(0, Translation::count(), 'Orphaned translations remain after restaurant delete');

        $this->assertDatabaseMissing('restaurants', ['id' => $this->restaurant->id]);
        $this->assertDatabaseMissing('menus', ['restaurant_id' => $this->restaurant->id]);
    }

    #[Test]
    public function test_delete_menu_cleans_section_item_group_option_translations(): void
    {
        $restaurantTranslations = Translation::where('translatable_type', Restaurant::class)->count();
        $zoneTranslations = $this->countTranslationsFor(Zone::class, $this->zones);

        $this->menu->delete();

        $this->assertSame(0, $this->countTranslationsFor(MenuSection::class, $this->sections));
        $this->assertSame(0, $this->countTranslationsFor(MenuItem::class, $this->items));
        $this->assertSame(0, $this->countTranslationsFor(MenuOptionGroup::class, $this->groups));
        $this->assertSame(0, $this->countTranslationsFor(MenuOptionGroupOption::class, $this->options));

        $this->assertSame(
            $restaurantTranslations,
            Translation::where('translatable_type', Restaurant::class)->count(),
            'Restaurant translations should be untouched',
        );
        $this->assertSame(
            $zoneTranslations,
            $this->countTranslationsFor(Zone::class, $this->zones),
            'Zone translations should be untouched',
        );
    }

    #[Test]
    public function test_delete_section_cleans_descendants_and_preserves_siblings(): void
    {
        $section = $this->sections[0];
        $sectionItems = collect($this->items)->where('section_id', $section->id);
        $sectionGroups = collect($this->groups)->where('section_id', $section->id);
        $sectionOptions = collect($this->options)
            ->whereIn('group_id', $sectionGroups->pluck('id'));

        $other = $this->sections[1];

        $section->delete();

        $this->assertSame(0, Translation::where('translatable_type', MenuSection::class)
            ->where('translatable_id', $section->id)->count());
        $this->assertSame(0, $this->countTranslationsFor(MenuItem::class, $sectionItems));
        $this->assertSame(0, $this->countTranslationsFor(MenuOptionGroup::class, $sectionGroups));
        $this->assertSame(0, $this->countTranslationsFor(MenuOptionGroupOption::class, $sectionOptions));

        $this->assertGreaterThan(0, Translation::where('translatable_type', MenuSection::class)
            ->where('translatable_id', $other->id)->count());
    }

    #[Test]
    public function test_delete_option_group_cleans_options_translations(): void
    {
        $group = $this->groups[0];
        $groupOptions = collect($this->options)->where('group_id', $group->id);

        $group->delete();

        $this->assertSame(0, Translation::where('translatable_type', MenuOptionGroup::class)
            ->where('translatable_id', $group->id)->count());
        $this->assertSame(0, $this->countTranslationsFor(MenuOptionGroupOption::class, $groupOptions));
    }

    #[Test]
    public function test_delete_item_cleans_own_translations(): void
    {
        $item = $this->items[0];
        $this->assertGreaterThan(0, Translation::where('translatable_type', MenuItem::class)
            ->where('translatable_id', $item->id)->count());

        $item->delete();

        $this->assertSame(0, Translation::where('translatable_type', MenuItem::class)
            ->where('translatable_id', $item->id)->count());
    }

    #[Test]
    public function test_delete_option_cleans_own_translations(): void
    {
        $option = $this->options[0];
        $this->assertGreaterThan(0, Translation::where('translatable_type', MenuOptionGroupOption::class)
            ->where('translatable_id', $option->id)->count());

        $option->delete();

        $this->assertSame(0, Translation::where('translatable_type', MenuOptionGroupOption::class)
            ->where('translatable_id', $option->id)->count());
    }

    #[Test]
    public function test_each_delete_path_does_not_orphan(): void
    {
        Restaurant::query()->whereKey($this->restaurant->id)->get()->each->delete();

        $this->assertSame(0, Translation::count());
    }
}
