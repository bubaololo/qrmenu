<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class LlmRequestFailedException extends Exception
{
    /**
     * @param  array<string, mixed>  $telemetry
     */
    public function __construct(
        string $message,
        public readonly array $telemetry = [],
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function statusCode(): int
    {
        $msg = $this->getMessage();
        if (str_contains($msg, 'cURL error 28') || str_contains($msg, 'timed out')) {
            return 504;
        }

        return 503;
    }
}
