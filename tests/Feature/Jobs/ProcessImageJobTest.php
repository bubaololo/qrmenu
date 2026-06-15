<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessImageJob;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Services\ImageProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessImageJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeItem(?string $image = null): MenuItem
    {
        $restaurant = Restaurant::factory()->create();
        $menu = Menu::factory()->for($restaurant)->create();
        $section = MenuSection::factory()->for($menu)->create();

        return MenuItem::factory()->for($section, 'section')->create(['image' => $image]);
    }

    #[Test]
    public function test_failed_deletes_temp_original_and_logs(): void
    {
        $originalsDisk = config('image.originals_disk');
        Storage::fake($originalsDisk);

        Storage::disk($originalsDisk)->put('originals/temp.jpg', 'x');

        Log::spy();

        $job = new ProcessImageJob(
            MenuItem::class,
            123,
            1,
            'originals/temp.jpg',
            'menu-items',
            'base-uuid',
        );

        $job->failed(new \RuntimeException('boom'));

        Storage::disk($originalsDisk)->assertMissing('originals/temp.jpg');
        Log::shouldHaveReceived('error')
            ->withArgs(fn ($message) => $message === 'ProcessImageJob: permanently failed')
            ->once();
    }

    #[Test]
    public function test_failed_is_noop_when_temp_original_already_gone(): void
    {
        $originalsDisk = config('image.originals_disk');
        Storage::fake($originalsDisk);

        $job = new ProcessImageJob(
            MenuItem::class,
            123,
            1,
            'originals/missing.jpg',
            'menu-items',
            'base-uuid',
        );

        $job->failed(new \RuntimeException('boom'));

        Storage::disk($originalsDisk)->assertMissing('originals/missing.jpg');
    }

    #[Test]
    public function test_handle_throws_when_original_missing_and_image_never_written(): void
    {
        Storage::fake(config('image.originals_disk'));

        $item = $this->makeItem(image: null);

        $job = new ProcessImageJob(
            MenuItem::class,
            $item->id,
            $item->section->menu->restaurant_id,
            'originals/gone.jpg',
            'menu-items',
            'base-uuid',
        );

        $this->expectException(\RuntimeException::class);
        $job->handle(app(ImageProcessor::class));
    }

    #[Test]
    public function test_handle_is_idempotent_noop_when_image_already_processed(): void
    {
        Storage::fake(config('image.originals_disk'));

        $expected = 'menu-items/base-uuid.'.config('image.format');
        $item = $this->makeItem(image: $expected);

        $job = new ProcessImageJob(
            MenuItem::class,
            $item->id,
            $item->section->menu->restaurant_id,
            'originals/gone.jpg',
            'menu-items',
            'base-uuid',
        );

        // A prior attempt already wrote the image; the original was consumed.
        // This must not throw.
        $job->handle(app(ImageProcessor::class));

        $this->assertSame($expected, $item->fresh()->image);
    }

    #[Test]
    public function test_handle_is_noop_when_model_gone(): void
    {
        Storage::fake(config('image.originals_disk'));

        $job = new ProcessImageJob(
            MenuItem::class,
            999999,
            1,
            'originals/gone.jpg',
            'menu-items',
            'base-uuid',
        );

        // Target row no longer exists — nothing to do, must not throw.
        $job->handle(app(ImageProcessor::class));

        $this->expectNotToPerformAssertions();
    }
}
