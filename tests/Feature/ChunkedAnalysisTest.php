<?php

namespace Tests\Feature;

use App\Actions\AnalyzeMenuImageAction;
use App\Actions\SaveMenuAnalysisAction;
use App\Enums\MenuAnalysisStatus;
use App\Jobs\AnalyzeChunkJob;
use App\Jobs\AnalyzeMenuJob;
use App\Jobs\CropMenuItemImagesJob;
use App\Jobs\FinalizeAnalysisJob;
use App\Models\Menu;
use App\Models\MenuAnalysis;
use App\Models\Restaurant;
use App\Services\LlmCascadeService;
use Database\Seeders\PromptSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChunkedAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private MenuAnalysis $analysis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PromptSeeder::class);
        $this->restaurant = Restaurant::factory()->create();
    }

    #[Test]
    public function test_small_pack_does_not_dispatch_chain(): void
    {
        Bus::fake([AnalyzeChunkJob::class, FinalizeAnalysisJob::class]);

        $this->analysis = $this->makeAnalysis(imageCount: 3);

        // Small pack should take the non-chunked path; don't actually run it (fake cascade).
        // We only assert that Bus::chain is NOT dispatched.
        $cascade = Mockery::mock(LlmCascadeService::class);
        $cascade->shouldReceive('resolveProviders')->andReturn([]);
        $cascade->shouldReceive('executeWithFallback')->andThrow(new \RuntimeException('no providers — expected to fall through, test should not reach here for small'));
        $this->app->instance(LlmCascadeService::class, $cascade);

        try {
            (new AnalyzeMenuJob($this->analysis))->handle(
                app(AnalyzeMenuImageAction::class),
                $cascade,
                app(SaveMenuAnalysisAction::class),
            );
        } catch (\Throwable) {
            // Expected — fake cascade throws. We just want to verify Bus::chain wasn't called.
        }

        Bus::assertNotDispatched(AnalyzeChunkJob::class);
        Bus::assertNotDispatched(FinalizeAnalysisJob::class);
    }

    #[Test]
    public function test_large_pack_dispatches_chunk_chain(): void
    {
        Bus::fake();

        $this->analysis = $this->makeAnalysis(imageCount: 11);

        (new AnalyzeMenuJob($this->analysis))->handle(
            app(AnalyzeMenuImageAction::class),
            app(LlmCascadeService::class),
            app(SaveMenuAnalysisAction::class),
        );

        // 11 images @ chunk_size=4 → 3 chunks (4+4+3) + finalize.
        Bus::assertChained([AnalyzeChunkJob::class, AnalyzeChunkJob::class, AnalyzeChunkJob::class, FinalizeAnalysisJob::class]);
    }

    #[Test]
    public function test_chunk_zero_creates_menu_and_subsequent_chunks_append(): void
    {
        $this->analysis = $this->makeAnalysis(imageCount: 8);

        $cascade = Mockery::mock(LlmCascadeService::class);
        $cascade->shouldReceive('resolveProviders')->andReturn([]);
        $cascade->shouldReceive('executeWithFallback')->twice()->andReturn(
            ['text' => $this->chunkResponse('Mains', ['Pizza', 'Pasta'], bboxStart: 0), 'provider' => 'gemini', 'model' => 'flash', 'tier' => 0],
            ['text' => $this->chunkResponse('Desserts', ['Tiramisu'], bboxStart: 0), 'provider' => 'gemini', 'model' => 'flash', 'tier' => 0],
        );
        $this->app->instance(LlmCascadeService::class, $cascade);

        $paths = $this->analysis->image_paths;

        // Run chunks synchronously.
        (new AnalyzeChunkJob(
            $this->analysis->refresh(), array_slice($paths, 0, 4), 0, 2, 0,
        ))->handle(
            app(AnalyzeMenuImageAction::class),
            $cascade,
            app(SaveMenuAnalysisAction::class),
        );

        $this->analysis->refresh();
        $this->assertNotNull($this->analysis->result_menu_id, 'first chunk must create a menu');
        $menu = Menu::find($this->analysis->result_menu_id);
        $this->assertSame(1, $menu->sections()->count());

        (new AnalyzeChunkJob(
            $this->analysis, array_slice($paths, 4, 4), 1, 2, 4,
        ))->handle(
            app(AnalyzeMenuImageAction::class),
            $cascade,
            app(SaveMenuAnalysisAction::class),
        );

        $menu->refresh();
        $this->assertSame(2, $menu->sections()->count(), 'second chunk appends section');

        $sections = $menu->sections()->orderBy('sort_order')->get();
        $this->assertSame(0, $sections[0]->sort_order);
        $this->assertSame(1, $sections[1]->sort_order);

        // image_bbox.image_index on items from second chunk must be offset by 4.
        $secondSectionItem = $sections[1]->items()->first();
        $this->assertSame(4, $secondSectionItem->image_bbox['image_index']);
    }

    #[Test]
    public function test_restaurant_metadata_enriched_only_when_empty(): void
    {
        // Start with empty phone; keep currency (NOT NULL) as-is.
        $this->restaurant->update(['phone' => null]);

        $this->analysis = $this->makeAnalysis(imageCount: 12);
        $cascade = Mockery::mock(LlmCascadeService::class);
        $cascade->shouldReceive('resolveProviders')->andReturn([]);
        // Chunk 0: no restaurant info (empty name, no currency).
        // Chunk 1: name + currency + phone.
        // Chunk 2: different name — should be ignored.
        $cascade->shouldReceive('executeWithFallback')->times(3)->andReturn(
            ['text' => $this->chunkResponse('Mains', ['A'], restaurant: []), 'provider' => 'x', 'model' => 'y', 'tier' => 0],
            ['text' => $this->chunkResponse('Drinks', ['B'], restaurant: ['name' => 'Amélie', 'currency' => 'EUR', 'phone' => '+33 1 23']), 'provider' => 'x', 'model' => 'y', 'tier' => 0],
            ['text' => $this->chunkResponse('Extras', ['C'], restaurant: ['name' => 'SomethingElse', 'currency' => 'USD', 'phone' => '+1 555 0100']), 'provider' => 'x', 'model' => 'y', 'tier' => 0],
        );
        $this->app->instance(LlmCascadeService::class, $cascade);

        $all = $this->analysis->image_paths;
        foreach ([[0, array_slice($all, 0, 4), 0], [1, array_slice($all, 4, 4), 4], [2, array_slice($all, 8, 4), 8]] as [$idx, $paths, $offset]) {
            (new AnalyzeChunkJob($this->analysis->refresh(), $paths, $idx, 3, $offset))->handle(
                app(AnalyzeMenuImageAction::class),
                $cascade,
                app(SaveMenuAnalysisAction::class),
            );
        }

        $this->restaurant->refresh();
        // Phone was empty → chunk 1 fills it. Chunk 2's different phone is ignored.
        $this->assertSame('+33 1 23', $this->restaurant->phone);
        // Currency was already set by factory ('VND'). Chunk 1's EUR and chunk 2's USD are ignored.
        $this->assertSame('VND', $this->restaurant->currency);
        // Name is stored as a translation; initial value comes from chunk 1 and is not overwritten by chunk 2.
        $this->assertSame('Amélie', $this->restaurant->initialText('name'));
    }

    #[Test]
    public function test_first_chunk_without_items_still_creates_menu(): void
    {
        // Cover/title page scenario — chunk 0 parses restaurant info but no items.
        $this->analysis = $this->makeAnalysis(imageCount: 8);

        $cascade = Mockery::mock(LlmCascadeService::class);
        $cascade->shouldReceive('resolveProviders')->andReturn([]);
        $cascade->shouldReceive('executeWithFallback')->twice()->andReturn(
            [
                'text' => json_encode([
                    'restaurant' => ['name' => 'La Maison', 'currency' => 'EUR', 'primary_language' => 'fr'],
                    'menu_version' => ['source_images_count' => 1],
                    'sections' => [],
                ]),
                'provider' => 'x', 'model' => 'y', 'tier' => 0,
            ],
            ['text' => $this->chunkResponse('Mains', ['Steak'], bboxStart: 0), 'provider' => 'x', 'model' => 'y', 'tier' => 0],
        );
        $this->app->instance(LlmCascadeService::class, $cascade);

        $paths = $this->analysis->image_paths;

        (new AnalyzeChunkJob($this->analysis->refresh(), array_slice($paths, 0, 4), 0, 2, 0))->handle(
            app(AnalyzeMenuImageAction::class), $cascade, app(SaveMenuAnalysisAction::class),
        );

        $this->analysis->refresh();
        $this->assertNotNull($this->analysis->result_menu_id, 'empty chunk 0 must still create menu');
        $menu = Menu::find($this->analysis->result_menu_id);
        $this->assertSame(0, $menu->sections()->count());

        (new AnalyzeChunkJob($this->analysis, array_slice($paths, 4, 4), 1, 2, 4))->handle(
            app(AnalyzeMenuImageAction::class), $cascade, app(SaveMenuAnalysisAction::class),
        );

        $menu->refresh();
        $this->assertSame(1, $menu->sections()->count(), 'second chunk appends section to previously-empty menu');
    }

    #[Test]
    public function test_finalize_marks_completed_and_dispatches_crop(): void
    {
        Queue::fake();

        $this->analysis = $this->makeAnalysis(imageCount: 8);

        // Pre-create a menu for the analysis so finalize has something to complete.
        $menu = Menu::factory()->for($this->restaurant)->create();
        $this->analysis->update(['result_menu_id' => $menu->id]);

        (new FinalizeAnalysisJob($this->analysis))->handle();

        $this->analysis->refresh();
        $this->assertSame(MenuAnalysisStatus::Completed, $this->analysis->status);
        Queue::assertPushed(CropMenuItemImagesJob::class);
    }

    #[Test]
    public function test_chunk_failed_hook_marks_analysis_failed(): void
    {
        $this->analysis = $this->makeAnalysis(imageCount: 8);

        $job = new AnalyzeChunkJob($this->analysis, ['a.webp', 'b.webp', 'c.webp', 'd.webp'], 0, 2, 0);
        $job->failed(new \RuntimeException('simulated llm outage'));

        $this->analysis->refresh();
        $this->assertSame(MenuAnalysisStatus::Failed, $this->analysis->status);
        $this->assertStringContainsString('Chunk 1/2 failed', $this->analysis->error_message);
    }

    private function makeAnalysis(int $imageCount): MenuAnalysis
    {
        Storage::fake('local');

        // Minimal valid WebP (VP8L lossless, 1×1 white pixel).
        $webpBytes = base64_decode('UklGRiQAAABXRUJQVlA4TBgAAAAvAAAAAAfQ//73v/+BiOh/AP7/fgf//1//');
        // Minimal valid JPEG (1×1 gray).
        $jpegBytes = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD5/wBUigjs0aGI7/Oj/i/2uPzr/9k=');

        $paths = [];
        for ($i = 0; $i < $imageCount; $i++) {
            $paths[] = "menu-analyzer-uploads/prep_{$i}.webp";
            Storage::disk('local')->put($paths[$i], $webpBytes);
        }
        $originals = [];
        for ($i = 0; $i < $imageCount; $i++) {
            $originals[] = "menu-analyzer-uploads/orig_{$i}.jpg";
            Storage::disk('local')->put($originals[$i], $jpegBytes);
        }

        return MenuAnalysis::create([
            'restaurant_id' => $this->restaurant->id,
            'image_count' => $imageCount,
            'image_paths' => $paths,
            'original_image_paths' => $originals,
            'image_disk' => 'local',
            'vision_model' => null,
        ]);
    }

    /**
     * @param  list<string>  $itemNames
     * @param  array<string, mixed>  $restaurant
     */
    private function chunkResponse(string $category, array $itemNames, int $bboxStart = 0, array $restaurant = []): string
    {
        $items = [];
        foreach ($itemNames as $i => $name) {
            $items[] = [
                'name' => $name,
                'description' => null,
                'starred' => false,
                'sort_order' => $i,
                'price' => ['value' => 1000, 'min' => null, 'max' => null, 'unit' => null, 'original_text' => '1000'],
                'item_confidence' => 1.0,
                'image_bbox' => [
                    'image_index' => $bboxStart + $i,
                    'coords' => [0.1, 0.1, 0.3, 0.3],
                    'confidence' => 0.95,
                ],
                'variations' => [],
                'options' => [],
            ];
        }

        return json_encode([
            'restaurant' => array_merge(['currency' => null, 'primary_language' => null], $restaurant),
            'menu_version' => ['source_images_count' => 1],
            'sections' => [[
                'category_name' => $category,
                'category_icon' => null,
                'sort_order' => 0,
                'items' => $items,
            ]],
        ]);
    }
}
