<?php

namespace App\Services;

use App\Support\PreflightResult;
use Illuminate\Support\Facades\Log;
use Imagick;

class ImagePreflightApplier
{
    public function apply(string $sourcePath, PreflightResult $result): void
    {
        $llm = Log::channel('llm');
        $name = basename($sourcePath);

        $startedAt = microtime(true);
        $img = new Imagick($sourcePath);

        $rawW = $img->getImageWidth();
        $rawH = $img->getImageHeight();
        $exifOrient = $img->getImageOrientation();

        // Apply EXIF orientation physically. This must match what the service
        // showed the LLM, so $result->rotationCw is interpreted as additional
        // rotation on top of the EXIF-corrected pixels.
        $img->autoOrient();

        $beforeW = $img->getImageWidth();
        $beforeH = $img->getImageHeight();
        $exifApplied = $exifOrient !== Imagick::ORIENTATION_UNDEFINED && $exifOrient !== Imagick::ORIENTATION_TOPLEFT;

        // EXIF was applied first via autoOrient(). LLM sees the EXIF-corrected
        // image and is the authoritative rotation signal: it returns 0 when
        // EXIF was right, and the corrective rotation when EXIF was wrong
        // (e.g. Samsung Galaxy bug where landscape captures get tagged with
        // Orientation=6). We trust the LLM either way.
        $llm->info('Preflight apply start', [
            'path' => $name,
            'rotation_cw' => $result->rotationCw,
            'content_bbox' => $result->contentBbox,
            'exif_orientation' => $exifOrient,
            'raw_dims' => $rawW.'x'.$rawH,
            'before_dims' => $beforeW.'x'.$beforeH,
        ]);

        if ($result->rotationCw !== 0) {
            $img->rotateImage('#000', $result->rotationCw);
            $img->setImagePage(0, 0, 0, 0);
        }

        $cropApplied = false;
        if ($result->contentBbox !== null) {
            $cropApplied = $this->applyCrop($img, $result->contentBbox, $name);
        }

        $img->writeImage($sourcePath);
        $afterW = $img->getImageWidth();
        $afterH = $img->getImageHeight();
        $img->clear();
        $img->destroy();

        $llm->info('Preflight apply done', [
            'path' => $name,
            'after_dims' => $afterW.'x'.$afterH,
            'file_size_kb' => (int) round(filesize($sourcePath) / 1024),
            'exif_applied' => $exifApplied,
            'rotation_applied' => $result->rotationCw,
            'crop_applied' => $cropApplied,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox
     */
    private function applyCrop(Imagick $img, array $bbox, string $name): bool
    {
        [$x1, $y1, $x2, $y2] = $bbox;
        $w = $img->getImageWidth();
        $h = $img->getImageHeight();

        $cropX = max(0, (int) round($x1 * $w));
        $cropY = max(0, (int) round($y1 * $h));
        $cropW = min((int) round(($x2 - $x1) * $w), $w - $cropX);
        $cropH = min((int) round(($y2 - $y1) * $h), $h - $cropY);

        if ($cropW < 100 || $cropH < 100) {
            Log::channel('llm')->warning('Preflight apply fallback', [
                'path' => $name,
                'reason' => 'crop_too_small',
                'crop_dims' => $cropW.'x'.$cropH,
            ]);

            return false;
        }

        $img->cropImage($cropW, $cropH, $cropX, $cropY);
        $img->setImagePage(0, 0, 0, 0);

        return true;
    }
}
