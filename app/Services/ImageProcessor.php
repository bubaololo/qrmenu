<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class ImageProcessor
{
    public function processAndStore(
        string $sourcePath,
        string $targetDir,
        string $baseName,
    ): array {
        $disk = config('image.disk');
        $format = config('image.format');
        $quality = config('image.quality');
        $mainWidth = config('image.main.width');
        $thumbWidth = config('image.thumb.width');

        $img = new \Imagick($sourcePath);
        $img->autoOrient();

        $main = clone $img;
        if ($main->getImageWidth() > $mainWidth) {
            $main->resizeImage($mainWidth, $mainWidth, \Imagick::FILTER_LANCZOS, 1, true);
        }
        $main->setImageFormat($format);
        $main->setImageCompressionQuality($quality);

        $thumb = clone $img;
        $thumb->thumbnailImage($thumbWidth, $thumbWidth, true);
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
