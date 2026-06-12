<?php

namespace Tests\Unit;

use App\Services\ImageProcessor;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImageProcessorTest extends TestCase
{
    private function makeSourceImage(int $width, int $height): string
    {
        $img = new \Imagick;
        $img->newImage($width, $height, 'red');
        $img->setImageFormat('jpeg');
        $path = tempnam(sys_get_temp_dir(), 'imgproc_').'.jpg';
        $img->writeImage($path);
        $img->clear();

        return $path;
    }

    private function storedDimensions(string $path): array
    {
        $img = new \Imagick;
        $img->readImageBlob(Storage::disk(config('image.disk'))->get($path));

        return [$img->getImageWidth(), $img->getImageHeight()];
    }

    #[Test]
    public function test_large_source_is_downscaled_to_profile_widths(): void
    {
        Storage::fake(config('image.disk'));
        $src = $this->makeSourceImage(2000, 1500);

        [$mainPath, $thumbPath] = app(ImageProcessor::class)
            ->processAndStore($src, 'menu-items', 'big');
        unlink($src);

        $this->assertSame([1024, 768], $this->storedDimensions($mainPath));
        $this->assertSame([400, 300], $this->storedDimensions($thumbPath));
    }

    #[Test]
    public function test_small_source_is_never_upscaled(): void
    {
        Storage::fake(config('image.disk'));
        $src = $this->makeSourceImage(150, 100);

        [$mainPath, $thumbPath] = app(ImageProcessor::class)
            ->processAndStore($src, 'menu-items', 'small');
        unlink($src);

        $this->assertSame([150, 100], $this->storedDimensions($mainPath));
        $this->assertSame([150, 100], $this->storedDimensions($thumbPath));
    }

    #[Test]
    public function test_banner_profile_uses_wider_targets(): void
    {
        Storage::fake(config('image.disk'));
        $src = $this->makeSourceImage(3200, 1600);

        [$mainPath, $thumbPath] = app(ImageProcessor::class)
            ->processAndStore($src, 'restaurants', 'cover', 'banner');
        unlink($src);

        $this->assertSame([1600, 800], $this->storedDimensions($mainPath));
        $this->assertSame([800, 400], $this->storedDimensions($thumbPath));
    }
}
