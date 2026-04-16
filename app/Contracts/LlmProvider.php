<?php

namespace App\Contracts;

use Prism\Prism\Contracts\Message;
use Prism\Prism\Enums\Provider;

interface LlmProvider
{
    public function provider(): Provider;

    public function model(): string;

    /**
     * @param  Message[]  $messages
     * @param  array<string, mixed>  $logContext
     * @return array{text: string, usage: array{input_tokens: ?int, output_tokens: ?int}}
     */
    public function execute(array $messages, array $logContext = []): array;
}
