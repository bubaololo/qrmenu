<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class ImageProcessor
{
    /**
     * Resize, convert and store an image with its thumbnail.
     *
     * @param  string  $profile  Size profile from config('image.profiles'):
     *                           'default' (items), 'banner' (wide cover), 'logo' (small)
     * @return array{0: string, 1: string} [mainPath, thumbPath]
     */
    public function processAndStore(
        string $sourcePath,
        string $targetDir,
        string $baseName,
        string $profile = 'default',
    ): array {
        $disk = config('image.disk');
        $format = config('image.format');
        $quality = config('image.quality');
        $sizes = config('image.profiles.'.$profile) ?? config('image.profiles.default');
        $mainWidth = $sizes['main'];
        $thumbWidth = $sizes['thumb'];

        $img = new \Imagick($sourcePath);
        $img->autoOrient();

        $main = clone $img;
        if ($main->getImageWidth() > $mainWidth) {
            $main->resizeImage($mainWidth, $mainWidth, \Imagick::FILTER_LANCZOS, 1, true);
        }
        $main->setImageFormat($format);
        $main->setImageCompressionQuality($quality);

        $thumb = clone $img;
        if (max($thumb->getImageWidth(), $thumb->getImageHeight()) > $thumbWidth) {
            $thumb->thumbnailImage($thumbWidth, $thumbWidth, true);
        }
        $thumb->setImageFormat($format);
        $thumb->setImageCompressionQuality($quality);

        $filename = $baseName.'.'.$format;
        $thumbname = $baseName.'_thumb.'.$format;

        Storage::disk($disk)->put($targetDir.'/'.$filename, $main->getImagesBlob(), 'public');
        Storage::disk($disk)->put($targetDir.'/'.$thumbname, $thumb->getImagesBlob(), 'public');

        $main->clear();
        $main->destroy();
        $thumb->clear();
        $thumb->destroy();
        $img->clear();
        $img->destroy();

        return [$targetDir.'/'.$filename, $targetDir.'/'.$thumbname];
    }

    public function thumbPath(string $mainPath): string
    {
        $ext = pathinfo($mainPath, PATHINFO_EXTENSION);

        return preg_replace('/\.'.$ext.'$/', '_thumb.'.$ext, $mainPath);
    }
}
