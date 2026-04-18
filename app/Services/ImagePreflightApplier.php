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

        if ($result->isNoop()) {
            $llm->debug('Preflight apply skipped (noop)', ['path' => $name]);

            return;
        }

        $startedAt = microtime(true);
        $img = new Imagick($sourcePath);
        $img->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);

        $beforeW = $img->getImageWidth();
        $beforeH = $img->getImageHeight();

        $llm->info('Preflight apply start', [
            'path' => $name,
            'rotation_cw' => $result->rotationCw,
            'content_bbox' => $result->contentBbox,
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
