<?php

namespace Tests\Feature;

use App\Actions\SaveMenuAnalysisAction;
use App\Enums\PriceType;
use App\Models\MenuAddon;
use App\Models\MenuItem;
use App\Models\MenuVariation;
use App\Models\Restaurant;
use App\Models\TranslationField;
use App\Support\MenuJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SaveMenuAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private array $menuData;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $raw = file_get_contents(base_path('tests/llm_responce.json'));
        $this->menuData = MenuJson::decodeMenuFromLlmText($raw);
    }

    #[Test]
    public function test_creates_menu_with_correct_meta(): void
    {
        $menu = (new SaveMenuAnalysisAction)->handle($this->menuData, $this->restaurant->id, 1);

        $this->assertSame('en', $menu->source_locale);
        $this->assertSame(1, $menu->source_images_count);
    }

    #[Test]
    public function test_creates_sections_and_items(): void
    {
        (new SaveMenuAnalysisAction)->handle($this->menuData, $this->restaurant->id, 1);

        $this->assertDatabaseCount('menu_sections', 8);
        $this->assertDatabaseCount('menu_items', 50);
    }

    #[Test]
    public function test_saves_item_name_translations_as_initial(): void
    {
        (new SaveMenuAnalysisAction)->handle($this->menuData, $this->restaurant->id, 1);

        $this->assertDatabaseHas('translations', [
            'field_id' => TranslationField::where('name', 'name')->value('id'),
            'value' => 'Black coffee',
            'is_initial' => true,
        ]);

        $this->assertDatabaseHas('translations', [
            'field_id' => TranslationField::where('name', 'name')->value('id'),
            'value' => 'Matcha latte',
            'is_initial' => true,
        ]);
    }

    #[Test]
    public function test_saves_section_name_translations(): void
    {
        (new SaveMenuAnalysisAction)->handle($this->menuData, $this->restaurant->id, 1);

        $this->assertDatabaseHas('translations', [
            'field_id' => TranslationField::where('name', 'name')->value('id'),
            'value' => 'TRADITIONAL CAFE',
            'is_initial' => true,
        ]);
    }

    #[Test]
    public function test_creates_variations_and_their_options(): void
    {
        (new SaveMenuAnalysisAction)->handle($this->menuData, $this->restaurant->id, 1);

        // Per-item variation axes deduplicate to unique menu-level variations,
        // each with its (absolute-priced) options.
        $this->assertSame(1, MenuVariation::count());
        $this->assertSame(2, DB::table('menu_variation_options')->count());
        $variationIds = MenuVariation::pluck('id');
        $this->assertDatabaseHas('menu_variation_options', ['variation_id' => $variationIds->first()]);
    }

    #[Test]
    public function test_creates_atomic_addons(): void
    {
        (new SaveMenuAnalysisAction)->handle($this->menuData, $this->restaurant->id, 1);

        // The legacy grouped "ADD ON" block is flattened into atomic add-ons,
        // deduplicated by name + price across all items.
        $this->assertSame(3, MenuAddon::count());
    }

    #[Test]
    public function test_deduplicates_variations_and_addons_at_menu_level(): void
    {
        (new SaveMenuAnalysisAction)->handle($this->menuData, $this->restaurant->id, 1);

        // Deduped entities are linked back to their items via pivot rows.
        $this->assertSame(1, MenuVariation::count());
        $this->assertSame(3, MenuAddon::count());
        $this->assertSame(12, DB::table('menu_item_variation')->count());
        $this->assertSame(45, DB::table('menu_item_addon')->count());
    }

    #[Test]
    public function test_infers_price_type_fixed(): void
    {
        (new SaveMenuAnalysisAction)->handle($this->menuData, $this->restaurant->id, 1);

        $item = MenuItem::where('price_original_text', '35.000')->firstOrFail();

        $this->assertSame(PriceType::Fixed, $item->price_type);
        $this->assertSame('35000.00', $item->price_value);
    }

    #[Test]
    public function test_infers_price_type_variable(): void
    {
        (new SaveMenuAnalysisAction)->handle($this->menuData, $this->restaurant->id, 1);

        // The fixture has 1 variable-price item
        $this->assertDatabaseHas('menu_items', ['price_type' => PriceType::Variable->value]);
    }

    #[Test]
    public function test_updates_restaurant_fields(): void
    {
        (new SaveMenuAnalysisAction)->handle($this->menuData, $this->restaurant->id, 1);

        $this->restaurant->refresh();

        $this->assertSame('VND', $this->restaurant->currency);
        $this->assertSame('en', $this->restaurant->primary_language);
    }

    #[Test]
    public function test_saves_restaurant_name(): void
    {
        (new SaveMenuAnalysisAction)->handle($this->menuData, $this->restaurant->id, 1);

        $this->assertSame('Amélie Pâtisserie et Café', $this->restaurant->fresh()->name);
    }

    #[Test]
    public function test_preserves_image_bbox_with_confidence(): void
    {
        $data = [
            'restaurant' => ['currency' => 'USD', 'primary_language' => 'en'],
            'sections' => [
                [
                    'category_name' => 'Mains',
                    'items' => [
                        [
                            'name' => 'Dish A',
                            'price' => ['original_text' => '10'],
                            'image_bbox' => [
                                'image_index' => 0,
                                'coords' => [0.1, 0.2, 0.4, 0.5],
                                'confidence' => 0.87,
                                'extra_unknown_field' => 'drop me',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        (new SaveMenuAnalysisAction)->handle($data, $this->restaurant->id, 1);

        $item = MenuItem::first();
        $this->assertSame([
            'image_index' => 0,
            'coords' => [0.1, 0.2, 0.4, 0.5],
            'confidence' => 0.87,
        ], $item->image_bbox);
    }

    #[Test]
    public function test_legacy_mixed_primary_language_resolves_to_concrete_source_locale(): void
    {
        // A menu has exactly one concrete original language. Should an old prompt
        // still emit the legacy 'mixed', it is resolved to the restaurant's
        // primary_language so source_locale and the is_initial rows agree.
        $this->restaurant->update(['primary_language' => 'en']);

        $data = [
            'restaurant' => ['currency' => 'USD', 'primary_language' => 'mixed'],
            'sections' => [
                [
                    'category_name' => 'Hotpots',
                    'items' => [
                        ['name' => 'Phở bò', 'price' => ['original_text' => '10']],
                    ],
                ],
            ],
        ];

        $menu = (new SaveMenuAnalysisAction)->handle($data, $this->restaurant->id, 1);

        // 'mixed' is never stored — resolved to the concrete primary_language.
        $this->assertSame('en', $menu->source_locale);

        // Initial translations land under that same concrete locale.
        $nameFieldId = TranslationField::where('name', 'name')->value('id');
        $this->assertDatabaseHas('translations', [
            'field_id' => $nameFieldId,
            'locale' => 'en',
            'value' => 'Hotpots',
            'is_initial' => true,
        ]);
        $this->assertDatabaseHas('translations', [
            'field_id' => $nameFieldId,
            'locale' => 'en',
            'value' => 'Phở bò',
            'is_initial' => true,
        ]);
    }

    #[Test]
    public function test_throws_when_menu_has_no_items(): void
    {
        $this->expectException(\RuntimeException::class);

        (new SaveMenuAnalysisAction)->handle(['sections' => []], $this->restaurant->id, 1);
    }

    #[Test]
    public function test_rolls_back_transaction_on_error(): void
    {
        $badData = $this->menuData;
        // Corrupt the first section to trigger a failure mid-way
        $badData['restaurant']['currency'] = null;
        // Force a failure by injecting an invalid item
        $badData['sections'][0]['items'][] = ['price' => ['original_text' => str_repeat('x', 70000)]];

        try {
            (new SaveMenuAnalysisAction)->handle($badData, $this->restaurant->id, 1);
        } catch (\Throwable) {
        }

        // Even if the above didn't throw, sections from a partial run shouldn't persist
        // without the menu record — foreign key constraint enforces this naturally.
        // Verify there are no orphaned sections if menu was never committed.
        $this->assertDatabaseCount('menu_sections', 0);
    }
}
