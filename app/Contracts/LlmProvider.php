<?php

namespace App\Contracts;

use Prism\Prism\Contracts\Message;
use Prism\Prism\Enums\Provider;

interface LlmProvider
{
    public function provider(): Provider;

    public function model(): string;

    public function timeoutSeconds(): int;

    /**
     * @param  Message[]  $messages
     * @param  array<string, mixed>  $logContext
     */
    public function execute(array $messages, array $logContext = []): string;
}
