<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_it_removes_script_tags_and_their_content(): void
    {
        $clean = HtmlSanitizer::clean('<p>Hello</p><script>alert("xss")</script>');

        $this->assertStringContainsString('Hello', $clean);
        $this->assertStringNotContainsString('script', strtolower($clean));
        $this->assertStringNotContainsString('alert', $clean);
    }

    public function test_it_strips_inline_event_handlers(): void
    {
        $clean = HtmlSanitizer::clean('<p onclick="steal()" onmouseover="x()">Click me</p>');

        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onmouseover', $clean);
        $this->assertStringContainsString('Click me', $clean);
    }

    public function test_it_removes_javascript_urls(): void
    {
        $clean = HtmlSanitizer::clean('<a href="javascript:alert(1)">bad link</a>');

        $this->assertStringNotContainsString('javascript:', strtolower($clean));
        $this->assertStringContainsString('bad link', $clean);
    }

    public function test_it_removes_dangerous_iframes_and_objects(): void
    {
        $clean = HtmlSanitizer::clean('<iframe src="https://evil.test"></iframe><object data="x"></object><p>safe</p>');

        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringNotContainsString('<object', $clean);
        $this->assertStringContainsString('safe', $clean);
    }

    public function test_it_preserves_safe_formatting_tags(): void
    {
        $clean = HtmlSanitizer::clean('<h2>Title</h2><p><strong>Bold</strong> and <em>italic</em></p><ul><li>One</li><li>Two</li></ul>');

        $this->assertStringContainsString('<h2>', $clean);
        $this->assertStringContainsString('<strong>', $clean);
        $this->assertStringContainsString('<em>', $clean);
        $this->assertStringContainsString('<ul>', $clean);
        $this->assertStringContainsString('<li>', $clean);
    }

    public function test_it_keeps_safe_links(): void
    {
        $clean = HtmlSanitizer::clean('<a href="https://example.com/reading">Recommended reading</a>');

        $this->assertStringContainsString('https://example.com/reading', $clean);
        $this->assertStringContainsString('Recommended reading', $clean);
    }

    public function test_it_unwraps_unknown_tags_but_keeps_text(): void
    {
        $clean = HtmlSanitizer::clean('<custom-widget>Inner text</custom-widget>');

        $this->assertStringContainsString('Inner text', $clean);
        $this->assertStringNotContainsString('custom-widget', $clean);
    }

    public function test_plain_text_extraction_flattens_markup(): void
    {
        $text = HtmlSanitizer::toPlainText('<h2>Hello</h2><p>World <strong>now</strong></p><script>bad()</script>');

        $this->assertStringContainsString('Hello', $text);
        $this->assertStringContainsString('World', $text);
        $this->assertStringContainsString('now', $text);
        $this->assertStringNotContainsString('bad', $text);
    }
}
