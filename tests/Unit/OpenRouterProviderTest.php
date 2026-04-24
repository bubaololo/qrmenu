<?php

namespace Tests\Unit;

use App\Llm\OpenRouterProvider;
use ReflectionMethod;
use Tests\TestCase;

class OpenRouterProviderTest extends TestCase
{
    public function test_timeout_seconds_matches_config(): void
    {
        config()->set('services.openai_compatible.http_timeout_seconds', 900);

        $provider = new OpenRouterProvider;
        $timeout = $this->invokeTimeoutSeconds($provider);

        $this->assertSame(900, $timeout);
    }

    public function test_timeout_seconds_reflects_large_config_value(): void
    {
        config()->set('services.openai_compatible.http_timeout_seconds', 3600);

        $provider = new OpenRouterProvider;
        $timeout = $this->invokeTimeoutSeconds($provider);

        $this->assertSame(3600, $timeout);
    }

    private function invokeTimeoutSeconds(OpenRouterProvider $provider): int
    {
        $method = new ReflectionMethod($provider, 'timeoutSeconds');
        $method->setAccessible(true);

        return (int) $method->invoke($provider);
    }
}
