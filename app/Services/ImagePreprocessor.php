<?php

namespace App\Services;

use App\Support\PreprocessResult;
use Illuminate\Support\Facades\Log;
use Imagick;

class ImagePreprocessor
{
    public function preprocess(string $sourcePath): PreprocessResult
    {
        $img = new Imagick($sourcePath);
        $meta = [
            'original_width' => $img->getImageWidth(),
            'original_height' => $img->getImageHeight(),
            'original_size_kb' => (int) round(filesize($sourcePath) / 1024),
            'original_format' => strtolower($img->getImageFormat()),
            'exif_orientation' => $img->getImageOrientation(),
        ];

        // 1. Trim white margins (fuzz 10% for scanned menus)
        $beforeTrim = [$img->getImageWidth(), $img->getImageHeight()];
        $img->trimImage(0.1 * Imagick::getQuantum());
        $img->setImagePage(0, 0, 0, 0);
        $afterTrim = [$img->getImageWidth(), $img->getImageHeight()];
        if ($beforeTrim !== $afterTrim) {
            $meta['trim_from'] = $beforeTrim;
            $meta['trim_to'] = $afterTrim;
        }

        // 2. Deskew slight tilt (up to ~5°)
        $img->deskewImage(40);

        // 3. Auto-level contrast
        $img->autoLevelImage();

        // 4. Resize if wider than max_width
        $maxWidth = (int) config('image.preprocess.max_width', 2400);
        if ($img->getImageWidth() > $maxWidth) {
            $meta['resize_from'] = [$img->getImageWidth(), $img->getImageHeight()];
            $img->resizeImage($maxWidth, 0, Imagick::FILTER_LANCZOS, 1);
        }

        // 5. Convert to WebP
        $format = config('image.preprocess.format', 'webp');
        $quality = (int) config('image.preprocess.quality', 85);
        $img->setImageFormat($format);
        $img->setImageCompressionQuality($quality);

        // 6. Write to temp file
        $tmpPath = tempnam(sys_get_temp_dir(), 'prep_').'.'.$format;
        $img->writeImage($tmpPath);

        $meta['final_width'] = $img->getImageWidth();
        $meta['final_height'] = $img->getImageHeight();
        $meta['final_size_kb'] = (int) round(filesize($tmpPath) / 1024);

        $img->clear();
        $img->destroy();

        Log::channel('llm')->info('Image preprocessed', array_merge(['source' => basename($sourcePath)], $meta));

        return new PreprocessResult($tmpPath, $meta);
    }
}
