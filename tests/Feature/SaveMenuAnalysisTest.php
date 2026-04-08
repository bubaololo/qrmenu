<?php

namespace Tests\Feature;

use App\Actions\SaveMenuAnalysisAction;
use App\Enums\PriceType;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Support\MenuJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertFalse($menu->is_active);
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
            'field' => 'name',
            'value' => 'Black coffee',
            'is_initial' => true,
        ]);

        $this->assertDatabaseHas('translations', [
            'field' => 'name',
            'value' => 'Matcha latte',
            'is_initial' => true,
        ]);
    }

    #[Test]
    public function test_saves_section_name_translations(): void
    {
        (new SaveMenuAnalysisAction)->handle($this->menuData, $this->restaurant->id, 1);

        $this->assertDatabaseHas('translations', [
            'field' => 'name',
            'value' => 'TRADITIONAL CAFE',
            'is_initial' => true,
        ]);
    }

    #[Test]
    public function test_creates_variations_and_their_options(): void
    {
        (new SaveMenuAnalysisAction)->handle($this->menuData, $this->restaurant->id, 1);

        $this->assertDatabaseCount('item_variations', 12);
        $this->assertDatabaseCount('variation_options', 24); // 12 variations × 2 (hot/iced)
    }

    #[Test]
    public function test_creates_option_groups_and_their_options(): void
    {
        (new SaveMenuAnalysisAction)->handle($this->menuData, $this->restaurant->id, 1);

        $this->assertDatabaseCount('item_option_groups', 15);
        $this->assertDatabaseCount('item_option_group_options', 45); // 15 groups × 3 options
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
    public function test_saves_restaurant_name_translation(): void
    {
        (new SaveMenuAnalysisAction)->handle($this->menuData, $this->restaurant->id, 1);

        $this->assertDatabaseHas('translations', [
            'field' => 'name',
            'value' => 'Amélie Pâtisserie et Café',
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
