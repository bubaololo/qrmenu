<?php

namespace App\Support;

final readonly class PreflightResult
{
    /**
     * @param  int  $rotationCw  0|90|180|270 degrees CW to apply for readable orientation
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $contentBbox  Normalized [x1,y1,x2,y2] of menu within frame, or null if fills frame
     * @param  string  $quality  'good'|'blurry'|'glare'|'dark'
     */
    public function __construct(
        public int $rotationCw,
        public ?array $contentBbox,
        public string $quality,
    ) {}

    public static function noop(): self
    {
        return new self(0, null, 'good');
    }

    public function isNoop(): bool
    {
        return $this->rotationCw === 0 && $this->contentBbox === null;
    }
}
