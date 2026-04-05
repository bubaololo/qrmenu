<?php

namespace Tests\Unit;

use App\Support\MenuJson;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MenuJsonDecodeTest extends TestCase
{
    #[Test]
    public function it_decodes_clean_json_object(): void
    {
        $json = '{"restaurant":{"currency":"VND"},"sections":[{"items":[]}]}';

        $menu = MenuJson::decodeMenuFromLlmText($json);

        $this->assertSame('VND', $menu['restaurant']['currency']);
        $this->assertCount(1, $menu['sections']);
    }

    #[Test]
    public function it_extracts_json_when_trailing_prose_breaks_plain_json_decode(): void
    {
        $raw = <<<'TXT'
Here is the menu JSON:

{"restaurant":{"name":{"vi":"X","en":"Y"}},"sections":[{"category_name":{"vi":"A","en":"B"},"items":[]}]}

Hope this helps!
TXT;

        $menu = MenuJson::decodeMenuFromLlmText($raw);

        $this->assertSame('Y', $menu['restaurant']['name']['en']);
        $this->assertSame('B', $menu['sections'][0]['category_name']['en']);
    }

    #[Test]
    public function it_strips_markdown_fences(): void
    {
        $raw = "```json\n{\"sections\":[]}\n```";

        $menu = MenuJson::decodeMenuFromLlmText($raw);

        $this->assertArrayHasKey('sections', $menu);
    }

    #[Test]
    public function it_wraps_top_level_json_array_as_single_section(): void
    {
        $raw = '[{"name":{"vi":"N","en":"N"}}]';

        $menu = MenuJson::decodeMenuFromLlmText($raw);

        $this->assertArrayHasKey('sections', $menu);
        $this->assertSame('N', $menu['sections'][0]['items'][0]['name']['en']);
    }
}
