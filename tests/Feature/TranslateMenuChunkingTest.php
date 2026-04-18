<?php

namespace Tests\Feature;

use App\Exceptions\LlmRequestFailedException;
use App\Jobs\TranslateChunkJob;
use App\Jobs\TranslateMenuJob;
use App\Llm\DeepSeekTextProvider;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Restaurant;
use Database\Seeders\PromptSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TranslateMenuChunkingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PromptSeeder::class);
    }

    #[Test]
    public function test_orchestrator_dispatches_bus_chain_of_chunk_jobs(): void
    {
        config(['llm.translation.chunk_lines' => 5]);
        Bus::fake();

        $menu = $this->makeMenuWithItems(itemCount: 20);

        (new TranslateMenuJob($menu, 'ru'))->handle();

        // 20 items + 1 section + R|name = 22 lines → ceil(22/5) = 5 chunks.
        Bus::assertChained([
            TranslateChunkJob::class,
            TranslateChunkJob::class,
            TranslateChunkJob::class,
            TranslateChunkJob::class,
            TranslateChunkJob::class,
        ]);
    }

    #[Test]
    public function test_small_menu_uses_single_chunk(): void
    {
        config(['llm.translation.chunk_lines' => 80]);
        Bus::fake();

        $menu = $this->makeMenuWithItems(itemCount: 3);

        (new TranslateMenuJob($menu, 'ru'))->handle();

        Bus::assertChained([TranslateChunkJob::class]);
    }

    #[Test]
    public function test_chunk_job_calls_provider_and_writes_translations(): void
    {
        $menu = $this->makeMenuWithItems(itemCount: 2);
        $menu->load(['sections.items']);
        $itemA = $menu->sections->first()->items->first();
        $itemB = $menu->sections->first()->items->last();
        $section = $menu->sections->first();

        // TSV lines that reference real IDs.
        $chunkLines = [
            "S|{$section->id}|Mains",
            "I|{$itemA->id}|Dish 0|",
            "I|{$itemB->id}|Dish 1|",
        ];

        $responseTsv = implode("\n", [
            "S|{$section->id}|Основное",
            "I|{$itemA->id}|Блюдо 0|",
            "I|{$itemB->id}|Блюдо 1|",
        ]);

        $provider = Mockery::mock(DeepSeekTextProvider::class);
        $provider->shouldReceive('execute')->once()->andReturn([
            'text' => $responseTsv,
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ]);

        (new TranslateChunkJob($menu, 'ru', $chunkLines, 0, 1))->handle($provider);

        $this->assertSame('Основное', $section->translate('name', 'ru'));
        $this->assertSame('Блюдо 0', $itemA->translate('name', 'ru'));
        $this->assertSame('Блюдо 1', $itemB->translate('name', 'ru'));
    }

    #[Test]
    public function test_chunk_job_retries_on_failure(): void
    {
        $menu = $this->makeMenuWithItems(itemCount: 1);

        $job = new TranslateChunkJob($menu, 'ru', ['S|1|Mains'], 0, 3);
        $this->assertSame(3, $job->tries);
        $this->assertSame([30, 60, 120], $job->backoff());
    }

    #[Test]
    public function test_chunk_failed_hook_logs(): void
    {
        $menu = $this->makeMenuWithItems(itemCount: 1);

        $job = new TranslateChunkJob($menu, 'ru', ['S|1|Mains'], 2, 5);

        // Just make sure failed() runs without throwing — Laravel calls it after tries exhausted.
        $job->failed(new LlmRequestFailedException('rate limit', []));
        $this->assertTrue(true);
    }

    private function makeMenuWithItems(int $itemCount): Menu
    {
        $restaurant = Restaurant::factory()->create();
        $menu = Menu::factory()->for($restaurant)->create(['source_locale' => 'vi']);
        $section = MenuSection::factory()->for($menu)->create();
        $section->setTranslation('name', 'vi', 'Mains', isInitial: true);

        for ($i = 0; $i < $itemCount; $i++) {
            $item = MenuItem::factory()->for($section, 'section')->create(['sort_order' => $i]);
            $item->setTranslation('name', 'vi', "Dish {$i}", isInitial: true);
        }

        return $menu;
    }
}
