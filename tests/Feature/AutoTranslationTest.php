<?php

namespace Tests\Feature;

use App\Actions\SaveMenuAnalysisAction;
use App\Jobs\TranslateEntityJob;
use App\Jobs\TranslateMenuJob;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuOptionGroup;
use App\Models\MenuOptionGroupOption;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Models\Translation;
use App\Models\TranslationField;
use App\Support\MenuJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AutoTranslationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['llm.translation.auto_sync' => true]);
    }

    /**
     * Seed a menu where some items already have non-initial translations on
     * a target locale, so Menu::availableLocales() returns >1 locale and
     * auto-translation has somewhere to dispatch to.
     */
    private function makeTranslatedMenu(string $sourceLocale = 'vi', string $targetLocale = 'en'): Menu
    {
        $restaurant = Restaurant::factory()->create(['primary_language' => $sourceLocale]);
        $menu = Menu::factory()->create([
            'restaurant_id' => $restaurant->id,
            'source_locale' => $sourceLocale,
        ]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $seedItem = MenuItem::factory()->create(['section_id' => $section->id]);

        $nameFieldId = TranslationField::firstOrCreate(['name' => 'name'])->id;
        Translation::create([
            'translatable_type' => MenuItem::class,
            'translatable_id' => $seedItem->id,
            'locale' => $sourceLocale,
            'field_id' => $nameFieldId,
            'value' => 'seed source',
            'is_initial' => true,
        ]);
        Translation::create([
            'translatable_type' => MenuItem::class,
            'translatable_id' => $seedItem->id,
            'locale' => $targetLocale,
            'field_id' => $nameFieldId,
            'value' => 'seed translated',
            'is_initial' => false,
        ]);

        return $menu->fresh();
    }

    #[Test]
    public function test_creating_initial_translation_dispatches_when_target_locales_exist(): void
    {
        Bus::fake([TranslateEntityJob::class]);

        $menu = $this->makeTranslatedMenu(sourceLocale: 'vi', targetLocale: 'en');
        $section = $menu->sections()->first();
        $item = MenuItem::factory()->create(['section_id' => $section->id]);

        $item->setTranslation('name', 'vi', 'Phở bò', isInitial: true);

        Bus::assertDispatched(
            TranslateEntityJob::class,
            fn (TranslateEntityJob $job) => $job->entity->is($item)
                && $job->field === 'name'
                && $job->targetLocales === ['en'],
        );
    }

    #[Test]
    public function test_creating_initial_translation_skips_dispatch_when_no_target_locales(): void
    {
        Bus::fake([TranslateEntityJob::class]);

        $restaurant = Restaurant::factory()->create(['primary_language' => 'vi']);
        $menu = Menu::factory()->create([
            'restaurant_id' => $restaurant->id,
            'source_locale' => 'vi',
        ]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);

        $item->setTranslation('name', 'vi', 'Phở bò', isInitial: true);

        Bus::assertNotDispatched(TranslateEntityJob::class);
    }

    #[Test]
    public function test_updating_initial_value_dispatches_translate_entity_job(): void
    {
        $menu = $this->makeTranslatedMenu();
        $section = $menu->sections()->first();
        $item = MenuItem::factory()->create(['section_id' => $section->id]);
        $item->setTranslation('name', 'vi', 'Pho original', isInitial: true);

        Bus::fake([TranslateEntityJob::class]);

        $item->setTranslation('name', 'vi', 'Pho updated', isInitial: true);

        Bus::assertDispatched(
            TranslateEntityJob::class,
            fn (TranslateEntityJob $job) => $job->entity->is($item)
                && $job->field === 'name'
                && $job->targetLocales === ['en'],
        );
    }

    #[Test]
    public function test_updating_non_initial_translation_does_not_dispatch(): void
    {
        $menu = $this->makeTranslatedMenu();
        $section = $menu->sections()->first();
        $item = MenuItem::factory()->create(['section_id' => $section->id]);
        $item->setTranslation('name', 'vi', 'Pho original', isInitial: true);

        Bus::fake([TranslateEntityJob::class]);

        $item->setTranslation('name', 'en', 'Beef Pho (corrected)', isInitial: false);

        Bus::assertNotDispatched(TranslateEntityJob::class);
    }

    #[Test]
    public function test_setting_initial_to_same_value_does_not_dispatch(): void
    {
        $menu = $this->makeTranslatedMenu();
        $section = $menu->sections()->first();
        $item = MenuItem::factory()->create(['section_id' => $section->id]);
        $item->setTranslation('name', 'vi', 'Same value', isInitial: true);

        Bus::fake([TranslateEntityJob::class]);

        $item->setTranslation('name', 'vi', 'Same value', isInitial: true);

        Bus::assertNotDispatched(TranslateEntityJob::class);
    }

    #[Test]
    public function test_section_creation_triggers_translation_when_locales_exist(): void
    {
        Bus::fake([TranslateEntityJob::class]);

        $menu = $this->makeTranslatedMenu();
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $section->setTranslation('name', 'vi', 'Закуски', isInitial: true);

        Bus::assertDispatched(
            TranslateEntityJob::class,
            fn (TranslateEntityJob $job) => $job->entity instanceof MenuSection
                && $job->entity->is($section)
                && $job->field === 'name'
                && $job->targetLocales === ['en'],
        );
    }

    #[Test]
    public function test_option_group_creation_triggers_translation(): void
    {
        Bus::fake([TranslateEntityJob::class]);

        $menu = $this->makeTranslatedMenu();
        $section = $menu->sections()->first();
        $group = MenuOptionGroup::factory()->create(['section_id' => $section->id]);
        $group->setTranslation('name', 'vi', 'Đồ uống', isInitial: true);

        Bus::assertDispatched(
            TranslateEntityJob::class,
            fn (TranslateEntityJob $job) => $job->entity instanceof MenuOptionGroup
                && $job->entity->is($group),
        );
    }

    #[Test]
    public function test_option_creation_triggers_translation(): void
    {
        Bus::fake([TranslateEntityJob::class]);

        $menu = $this->makeTranslatedMenu();
        $section = $menu->sections()->first();
        $group = MenuOptionGroup::factory()->create(['section_id' => $section->id]);
        $option = MenuOptionGroupOption::factory()->create(['group_id' => $group->id]);
        $option->setTranslation('name', 'vi', 'Cay', isInitial: true);

        Bus::assertDispatched(
            TranslateEntityJob::class,
            fn (TranslateEntityJob $job) => $job->entity instanceof MenuOptionGroupOption
                && $job->entity->is($option),
        );
    }

    #[Test]
    public function test_restaurant_field_change_uses_active_menu_locales(): void
    {
        Bus::fake([TranslateEntityJob::class]);

        $menu = $this->makeTranslatedMenu();
        $restaurant = $menu->restaurant->fresh();

        $restaurant->setTranslation('name', 'vi', 'Quán Phở', isInitial: true);

        Bus::assertDispatched(
            TranslateEntityJob::class,
            fn (TranslateEntityJob $job) => $job->entity instanceof Restaurant
                && $job->field === 'name'
                && $job->targetLocales === ['en'],
        );
    }

    #[Test]
    public function test_restaurant_without_active_menu_does_not_dispatch(): void
    {
        Bus::fake([TranslateEntityJob::class]);

        $restaurant = Restaurant::factory()->create(['primary_language' => 'vi']);
        $restaurant->setTranslation('name', 'vi', 'Quán mới', isInitial: true);

        Bus::assertNotDispatched(TranslateEntityJob::class);
    }

    #[Test]
    public function test_non_initial_translate_chunk_writes_do_not_dispatch_entity_job(): void
    {
        Bus::fake([TranslateEntityJob::class]);

        $menu = $this->makeTranslatedMenu();
        $section = $menu->sections()->first();
        $item = MenuItem::factory()->create(['section_id' => $section->id]);

        $item->setTranslation('name', 'en', 'Translated', isInitial: false);

        Bus::assertNotDispatched(TranslateEntityJob::class);
    }

    /**
     * Bulk-save contract: SaveMenuAnalysisAction wraps the transaction in
     * Translation::withoutEvents(), so no Translation event listener should
     * fire during the save. We verify this by attaching an ad-hoc saved
     * listener and asserting it never ran — withoutEvents silences ALL
     * listeners on the model class for the closure's duration.
     */
    #[Test]
    public function test_save_menu_analysis_does_not_fire_translation_observers(): void
    {
        Bus::fake([TranslateEntityJob::class]);

        $savedCount = 0;
        Translation::saved(function () use (&$savedCount) {
            $savedCount++;
        });

        $restaurant = Restaurant::factory()->create();
        $menuData = MenuJson::decodeMenuFromLlmText(
            file_get_contents(base_path('tests/llm_responce.json')),
        );

        (new SaveMenuAnalysisAction)->handle($menuData, $restaurant->id, 1);

        $this->assertSame(
            0,
            $savedCount,
            'Translation observers must be silenced during bulk save (withoutEvents).',
        );
        Bus::assertNotDispatched(TranslateEntityJob::class);
    }

    #[Test]
    public function test_dispatch_for_all_target_locales_dispatches_one_job_per_non_source_locale(): void
    {
        Bus::fake([TranslateMenuJob::class]);

        $menu = $this->makeTranslatedMenu(sourceLocale: 'vi', targetLocale: 'en');

        TranslateMenuJob::dispatchForAllTargetLocales($menu);

        Bus::assertDispatchedTimes(TranslateMenuJob::class, 1);
        Bus::assertDispatched(
            TranslateMenuJob::class,
            fn (TranslateMenuJob $job) => $job->menu->is($menu) && $job->targetLocale === 'en',
        );
    }

    #[Test]
    public function test_dispatch_for_all_target_locales_dispatches_nothing_when_no_targets(): void
    {
        Bus::fake([TranslateMenuJob::class]);

        $restaurant = Restaurant::factory()->create(['primary_language' => 'vi']);
        $menu = Menu::factory()->create([
            'restaurant_id' => $restaurant->id,
            'source_locale' => 'vi',
        ]);

        TranslateMenuJob::dispatchForAllTargetLocales($menu);

        Bus::assertNotDispatched(TranslateMenuJob::class);
    }
}
