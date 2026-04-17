<?php

namespace App\Support;

readonly class PreprocessResult
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $path,
        public array $meta = [],
    ) {}
}
