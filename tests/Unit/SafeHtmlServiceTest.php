<?php

namespace Tests\Unit;

use App\Services\SafeHtmlService;
use PHPUnit\Framework\TestCase;

class SafeHtmlServiceTest extends TestCase
{
    public function test_it_removes_executable_markup_and_unsafe_urls(): void
    {
        $html = (new SafeHtmlService())->sanitize(
            '<h2 onclick="alert(1)">Title</h2><script>alert(1)</script><a href="javascript:alert(1)">bad</a><p>Safe</p>'
        );

        $this->assertStringContainsString('<h2>Title</h2>', $html);
        $this->assertStringContainsString('<p>Safe</p>', $html);
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }
}
