<?php

namespace Tests\Feature;

use App\Actions\GenerateQrCode;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenerateQrCodeTest extends TestCase
{
    #[Test]
    public function it_returns_png_response_with_correct_headers(): void
    {
        $response = (new GenerateQrCode)('https://example.com/abc');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=86400', (string) $response->headers->get('Cache-Control'));
        $this->assertTrue(str_starts_with($response->getContent(), "\x89PNG\r\n\x1a\n"));
    }

    #[Test]
    public function it_encodes_distinct_payloads_into_distinct_images(): void
    {
        $a = (new GenerateQrCode)('https://example.com/a')->getContent();
        $b = (new GenerateQrCode)('https://example.com/b')->getContent();

        $this->assertNotSame($a, $b);
    }
}
