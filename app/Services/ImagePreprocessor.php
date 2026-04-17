<?php

namespace App\Services;

use App\Support\PreprocessResult;
use Imagick;

class ImagePreprocessor
{
    public function preprocess(string $sourcePath): PreprocessResult
    {
        $img = new Imagick($sourcePath);
        $meta = [
            'original_width' => $img->getImageWidth(),
            'original_height' => $img->getImageHeight(),
        ];

        // 1. Auto-orient by EXIF (hardware rotation only, not content-based)
        $origOrientation = $img->getImageOrientation();
        $img->autoOrient();
        if ($origOrientation !== Imagick::ORIENTATION_TOPLEFT && $origOrientation !== 0) {
            $meta['exif_rotated'] = true;
        }

        // 2. Trim white margins (fuzz 10% for scanned menus)
        $img->trimImage(0.1 * Imagick::getQuantum());
        $img->setImagePage(0, 0, 0, 0);

        // 3. Deskew slight tilt (up to ~5°)
        $img->deskewImage(40);

        // 4. Auto-level contrast
        $img->autoLevelImage();

        // 5. Resize if wider than max_width
        $maxWidth = (int) config('image.preprocess.max_width', 2400);
        if ($img->getImageWidth() > $maxWidth) {
            $img->resizeImage($maxWidth, 0, Imagick::FILTER_LANCZOS, 1);
            $meta['resized_to'] = $maxWidth;
        }

        // 6. Convert to WebP
        $format = config('image.preprocess.format', 'webp');
        $quality = (int) config('image.preprocess.quality', 85);
        $img->setImageFormat($format);
        $img->setImageCompressionQuality($quality);

        // 7. Write to temp file
        $tmpPath = tempnam(sys_get_temp_dir(), 'prep_').'.'.$format;
        $img->writeImage($tmpPath);

        $meta['final_width'] = $img->getImageWidth();
        $meta['final_height'] = $img->getImageHeight();
        $meta['final_size_kb'] = (int) round(filesize($tmpPath) / 1024);

        $img->clear();
        $img->destroy();

        return new PreprocessResult($tmpPath, $meta);
    }
}
