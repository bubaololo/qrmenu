<?php

namespace Tests\Feature\Jobs;

use App\Jobs\DeleteImageFilesJob;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeleteImageFilesJobTest extends TestCase
{
    #[Test]
    public function test_handle_deletes_files_across_multiple_disks(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        Storage::disk('public')->put('restaurants/a.webp', 'x');
        Storage::disk('public')->put('restaurants/a_thumb.webp', 'x');
        Storage::disk('public')->put('menu-items/b.webp', 'x');
        Storage::disk('local')->put('originals/c.jpg', 'x');

        (new DeleteImageFilesJob([
            'public' => ['restaurants/a.webp', 'restaurants/a_thumb.webp', 'menu-items/b.webp'],
            'local' => ['originals/c.jpg'],
        ]))->handle();

        Storage::disk('public')->assertMissing('restaurants/a.webp');
        Storage::disk('public')->assertMissing('restaurants/a_thumb.webp');
        Storage::disk('public')->assertMissing('menu-items/b.webp');
        Storage::disk('local')->assertMissing('originals/c.jpg');
    }

    #[Test]
    public function test_handle_is_noop_for_missing_files(): void
    {
        Storage::fake('public');

        (new DeleteImageFilesJob([
            'public' => ['restaurants/missing.webp', 'restaurants/missing_thumb.webp'],
        ]))->handle();

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function test_handle_dedupes_and_skips_empty_lists(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('restaurants/a.webp', 'x');

        (new DeleteImageFilesJob([
            'public' => ['restaurants/a.webp', 'restaurants/a.webp', ''],
            'local' => [],
        ]))->handle();

        Storage::disk('public')->assertMissing('restaurants/a.webp');
    }

    #[Test]
    public function test_handle_removes_empty_sub_directories_but_keeps_top_level(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('menu-items/29/item_1575.webp', 'x');
        Storage::disk('public')->put('menu-items/29/item_1575_thumb.webp', 'x');
        Storage::disk('public')->put('menu-items/30/item_999.webp', 'x');

        (new DeleteImageFilesJob([
            'public' => ['menu-items/29/item_1575.webp', 'menu-items/29/item_1575_thumb.webp'],
        ]))->handle();

        $this->assertFalse(Storage::disk('public')->exists('menu-items/29'), 'Empty sub-dir should be removed');
        $this->assertTrue(Storage::disk('public')->exists('menu-items/30'), 'Sibling sub-dir with files should stay');
        $this->assertTrue(Storage::disk('public')->exists('menu-items'), 'Top-level dir must stay');
    }

    #[Test]
    public function test_handle_keeps_non_empty_sub_directory(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('menu-items/29/item_1.webp', 'x');
        Storage::disk('public')->put('menu-items/29/item_2.webp', 'still here');

        (new DeleteImageFilesJob([
            'public' => ['menu-items/29/item_1.webp'],
        ]))->handle();

        Storage::disk('public')->assertMissing('menu-items/29/item_1.webp');
        Storage::disk('public')->assertExists('menu-items/29/item_2.webp');
        $this->assertTrue(Storage::disk('public')->exists('menu-items/29'));
    }
}
