<?php

namespace Tests\Feature;

use App\Jobs\TranslateEntityJob;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Services\LlmCascadeService;
use Database\Seeders\PromptSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TranslateEntityJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PromptSeeder::class);
    }

    private function makeItemWithSource(): MenuItem
    {
        $restaurant = Restaurant::factory()->create();
        $menu = Menu::factory()->for($restaurant)->create(['source_locale' => 'vi']);
        $section = MenuSection::factory()->for($menu)->create();
        $section->setTranslation('name', 'vi', 'Mains', isInitial: true);

        $item = MenuItem::factory()->for($section, 'section')->create();
        $item->setTranslation('name', 'vi', 'Phở bò', isInitial: true);
        $item->setTranslation('description', 'vi', 'Súp mì với thịt bò', isInitial: true);

        return $item;
    }

    private function fakeCascadeReturning(string $tsv): LlmCascadeService
    {
        $cascade = Mockery::mock(LlmCascadeService::class);
        $cascade->shouldReceive('executeWithFallback')->andReturn([
            'text' => $tsv,
            'provider' => 'deepseek',
            'model' => 'deepseek-chat',
            'tier' => 0,
        ]);

        return $cascade;
    }

    #[Test]
    public function test_writes_only_specified_field_for_menu_item(): void
    {
        $item = $this->makeItemWithSource();

        // Pre-populate a manual correction on the en name. Job must not overwrite it
        // even though the LLM response below contains a name field.
        $item->setTranslation('name', 'en', 'Manual Beef Pho', isInitial: false);

        $tsvResponse = "I|{$item->id}|LLM Beef Pho|LLM beef noodle soup";
        $cascade = $this->fakeCascadeReturning($tsvResponse);

        (new TranslateEntityJob($item, ['en'], 'description'))->handle($cascade);

        $this->assertSame(
            'Manual Beef Pho',
            $item->fresh()->translate('name', 'en'),
            'Job must preserve manual correction of sibling field',
        );
        $this->assertSame(
            'LLM beef noodle soup',
            $item->fresh()->translate('description', 'en'),
            'Job must write only the requested field',
        );
    }

    #[Test]
    public function test_runs_one_call_per_target_locale(): void
    {
        $item = $this->makeItemWithSource();

        $cascade = Mockery::mock(LlmCascadeService::class);
        $cascade->shouldReceive('executeWithFallback')
            ->times(2)
            ->andReturnUsing(function ($messages, $providers, $analysis, $context) use ($item) {
                return [
                    'text' => "I|{$item->id}|Translated|",
                    'provider' => 'deepseek',
                    'model' => 'deepseek-chat',
                    'tier' => 0,
                ];
            });

        (new TranslateEntityJob($item, ['en', 'ru'], 'name'))->handle($cascade);

        $this->assertSame('Translated', $item->fresh()->translate('name', 'en'));
        $this->assertSame('Translated', $item->fresh()->translate('name', 'ru'));
    }

    #[Test]
    public function test_skips_when_target_locales_empty(): void
    {
        $item = $this->makeItemWithSource();

        $cascade = Mockery::mock(LlmCascadeService::class);
        $cascade->shouldNotReceive('executeWithFallback');

        (new TranslateEntityJob($item, [], 'name'))->handle($cascade);

        $this->assertTrue(true);
    }

    #[Test]
    public function test_ignores_lines_for_other_entity_ids(): void
    {
        $item = $this->makeItemWithSource();

        $otherId = $item->id + 999;
        $tsvResponse = implode("\n", [
            "I|{$otherId}|Wrong Entity|Wrong",
            "I|{$item->id}|Correct|Correct desc",
        ]);
        $cascade = $this->fakeCascadeReturning($tsvResponse);

        (new TranslateEntityJob($item, ['en'], 'name'))->handle($cascade);

        $this->assertSame('Correct', $item->fresh()->translate('name', 'en'));
    }

    #[Test]
    public function test_strips_markdown_code_fences(): void
    {
        $item = $this->makeItemWithSource();

        $tsvResponse = "```tsv\nI|{$item->id}|Fenced|fenced desc\n```";
        $cascade = $this->fakeCascadeReturning($tsvResponse);

        (new TranslateEntityJob($item, ['en'], 'name'))->handle($cascade);

        $this->assertSame('Fenced', $item->fresh()->translate('name', 'en'));
    }

    #[Test]
    public function test_writes_section_name_for_section_entity(): void
    {
        $restaurant = Restaurant::factory()->create();
        $menu = Menu::factory()->for($restaurant)->create(['source_locale' => 'vi']);
        $section = MenuSection::factory()->for($menu)->create();
        $section->setTranslation('name', 'vi', 'Khai vị', isInitial: true);

        $tsvResponse = "S|{$section->id}|Starters";
        $cascade = $this->fakeCascadeReturning($tsvResponse);

        (new TranslateEntityJob($section, ['en'], 'name'))->handle($cascade);

        $this->assertSame('Starters', $section->fresh()->translate('name', 'en'));
    }

    #[Test]
    public function test_retries_and_backoff_match_chunk_job(): void
    {
        $item = $this->makeItemWithSource();
        $job = new TranslateEntityJob($item, ['en'], 'name');

        $this->assertSame(3, $job->tries);
        $this->assertSame([30, 60, 120], $job->backoff());
    }
}
